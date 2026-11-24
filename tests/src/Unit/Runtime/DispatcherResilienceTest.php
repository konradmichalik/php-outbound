<?php

declare(strict_types=1);

/*
 * This file is part of the "php-outbound" Composer package.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Outbound\Tests\Unit\Runtime;

use KonradMichalik\Outbound\Exception\{ApiRateLimited, ApiTransportFailed, ApiUnauthorized, ApiUnavailable};
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\{CacheLayer, Dispatcher, Execution, Settings};
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Resilience\{DocumentApi, FlakyApi, RefreshApi};
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Result\ResultApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;
use function str_repeat;
use function sys_get_temp_dir;

require_once __DIR__.'/../Fixtures/ResilienceDefinitions.php';
require_once __DIR__.'/../Fixtures/ResultDefinitions.php';

/**
 * DispatcherResilienceTest.
 *
 * What a caller gets when the API or the network misbehaves: a refreshed token
 * after a 401, the last good answer instead of an outage, and revalidation.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Dispatcher::class)]
#[CoversClass(CacheLayer::class)]
#[CoversClass(Execution::class)]
final class DispatcherResilienceTest extends TestCase
{
    private const TEXT = ['content-type' => 'text/plain'];

    private MockTransport $transport;

    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->clock = new FakeClock();
    }

    #[Test]
    public function a401RefreshesTheTokenOnceAndResendsTheRequest(): void
    {
        $outbound = $this->outbound(RefreshApi::class);
        $this->tokens('tok1', 'tok2');
        $this->transport->on(RefreshApi::class, 'data')->respondWith(401)->respondWith(200, 'ok', self::TEXT);

        self::assertSame('ok', $outbound->api(RefreshApi::class)->data());

        $requests = $this->transport->calls(RefreshApi::class, 'data');
        self::assertCount(2, $requests);
        self::assertSame('Bearer tok1', $requests[0]->request->headers['Authorization']);
        self::assertSame('Bearer tok2', $requests[1]->request->headers['Authorization']);
        self::assertCount(2, $this->tokenCalls($outbound));
    }

    #[Test]
    public function aSecond401IsReturnedInsteadOfLoopingAndKeepsTheFreshToken(): void
    {
        $outbound = $this->outbound(RefreshApi::class);
        $this->tokens('tok1', 'tok2');
        $this->transport->on(RefreshApi::class, 'data')->respondWith(401);

        try {
            $outbound->api(RefreshApi::class)->data();
            self::fail('A repeated 401 must surface.');
        } catch (ApiUnauthorized $exception) {
            self::assertSame(401, $exception->status);
        }

        self::assertCount(2, $this->transport->calls(RefreshApi::class, 'data'), 'Exactly one resend.');
        self::assertCount(2, $this->tokenCalls($outbound), 'No third token request.');
    }

    #[Test]
    public function aNetworkFailureBecomesATransportExceptionAndCountsAgainstTheBreaker(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'flaky')->failWith('Connection refused');

        foreach ([1, 2] as $ignored) {
            try {
                $api->flaky();
                self::fail('The failure must surface.');
            } catch (ApiTransportFailed $exception) {
                self::assertSame(1794700130, $exception->getCode());
                self::assertSame('DocumentApi::flaky', $exception->endpoint);
                self::assertNull($exception->status);
            }
        }

        $this->expectException(ApiUnavailable::class);

        $api->flaky();
    }

    #[Test]
    public function theLastGoodAnswerIsServedWhenTheNetworkFailsWithinTheStaleWindow(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT)->failWith();
        self::assertSame('v1', $api->doc(1));

        $this->clock->advance(61);
        self::assertSame('v1', $api->doc(1), 'Expired but still inside the stale window.');

        $this->clock->advance(300);
        $this->expectException(ApiTransportFailed::class);

        $api->doc(1);
    }

    #[Test]
    public function theLastGoodAnswerIsServedWhileTheBreakerIsOpen(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT);
        $this->transport->on(DocumentApi::class, 'flaky')->respondWith(500);
        self::assertSame('v1', $api->doc(1));
        foreach ([1, 2] as $ignored) {
            try {
                $api->flaky();
            } catch (ApiUnavailable) {
            }
        }

        $this->clock->advance(61);

        self::assertSame('v1', $api->doc(1));
        self::assertSame(1, $this->transport->count(DocumentApi::class, 'doc'), 'The open breaker kept the request back.');
    }

    #[Test]
    public function anExpiredEntryIsRevalidatedWithItsEtag(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'doc')
            ->respondWith(200, 'v1', [...self::TEXT, 'etag' => '"v1"'])
            ->respondWith(304);
        self::assertSame('v1', $api->doc(1));

        $this->clock->advance(61);

        self::assertSame('v1', $api->doc(1));
        $requests = $this->transport->calls(DocumentApi::class, 'doc');
        self::assertArrayNotHasKey('If-None-Match', $requests[0]->request->headers);
        self::assertSame('"v1"', $requests[1]->request->headers['If-None-Match']);
    }

    #[Test]
    public function theEtagOfAReadGuardsTheNextWriteOfTheSameResource(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', [...self::TEXT, 'etag' => '"v1"']);
        $this->transport->on(DocumentApi::class, 'save')->respondWith(204);
        $api->doc(1);

        $api->save(1, ['title' => 'a']);
        $api->save(2, ['title' => 'b']);

        $writes = $this->transport->calls(DocumentApi::class, 'save');
        self::assertSame('"v1"', $writes[0]->request->headers['If-Match']);
        self::assertArrayNotHasKey('If-Match', $writes[1]->request->headers, 'Another resource has another ETag.');
    }

    #[Test]
    public function networkFailuresAreRetriedUpToMaxRetriesWithABackoff(): void
    {
        $api = $this->outbound(FlakyApi::class)->api(FlakyApi::class);
        $this->transport->on(FlakyApi::class, 'x')->failWith();

        try {
            $api->x();
            self::fail('The failure must surface after the last retry.');
        } catch (ApiTransportFailed) {
        }

        self::assertSame(3, $this->transport->count(FlakyApi::class, 'x'), 'One attempt and two retries.');
        self::assertCount(2, $this->clock->sleeps);
        self::assertLessThan($this->clock->sleeps[1], $this->clock->sleeps[0], 'The backoff grows.');
    }

    #[Test]
    public function aRetryThatSucceedsReturnsTheAnswer(): void
    {
        $api = $this->outbound(FlakyApi::class)->api(FlakyApi::class);
        $this->transport->on(FlakyApi::class, 'x')->failWith()->respondWith(200, 'ok', self::TEXT);

        self::assertSame('ok', $api->x());
        self::assertSame(2, $this->transport->count(FlakyApi::class, 'x'));
    }

    #[Test]
    public function aSuccessfulAnswerResetsTheFailuresCountedByTheBreaker(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'flaky')->respondWith(500);
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT);

        foreach ([1, 2, 3] as $attempt) {
            try {
                $api->flaky();
            } catch (ApiUnavailable) {
            }
            if (1 === $attempt) {
                $api->doc(1);
            }
        }

        self::assertSame(3, $this->transport->count(DocumentApi::class, 'flaky'), 'The breaker never reached its threshold of two.');
    }

    #[Test]
    public function aResponseMayHaveExactlyTheConfiguredSizeButNotOneByteMore(): void
    {
        $api = $this->outbound(DocumentApi::class, new Settings(maxResponseBytes: 10))->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'flaky')->respondWith(200, '0123456789', self::TEXT)->respondWith(200, '0123456789A', self::TEXT);

        self::assertSame('0123456789', $api->flaky());

        try {
            $api->flaky();
            self::fail('An oversized response must be rejected.');
        } catch (ApiTransportFailed $exception) {
            self::assertSame(1794700133, $exception->getCode());
            self::assertSame(200, $exception->status);
        }
    }

    #[Test]
    public function aConnectionWithoutTokensSendsA401BackToTheCaller(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'flaky')->respondWith(401);

        try {
            $api->flaky();
            self::fail('401 must surface.');
        } catch (ApiUnauthorized) {
        }

        self::assertSame(1, $this->transport->count(DocumentApi::class, 'flaky'), 'Nothing to refresh, so nothing is resent.');
    }

    #[Test]
    public function tooManyRequestsCountAsFailuresForTheBreaker(): void
    {
        $api = $this->outbound(DocumentApi::class)->api(DocumentApi::class);
        $this->transport->on(DocumentApi::class, 'flaky')->respondWith(429);

        foreach ([1, 2] as $ignored) {
            try {
                $api->flaky();
            } catch (ApiRateLimited) {
            }
        }

        try {
            $api->flaky();
            self::fail('The breaker is open after two failures.');
        } catch (ApiUnavailable) {
        }

        self::assertSame(2, $this->transport->count(DocumentApi::class, 'flaky'));
    }

    #[Test]
    public function aRawDownloadIsNotBufferedAgainstTheSizeLimit(): void
    {
        $api = $this->outbound(ResultApi::class, new Settings(maxResponseBytes: 10))->api(ResultApi::class);
        $this->transport->on(ResultApi::class, 'raw')->respondWith(200, str_repeat('x', 20), ['content-type' => 'application/octet-stream']);

        self::assertSame(str_repeat('x', 20), $api->raw()->content());
    }

    #[Test]
    public function aRevalidationKeepsItsConditionalHeaderWhenTheTokenIsRefreshed(): void
    {
        $api = $this->outbound(RefreshApi::class)->api(RefreshApi::class);
        $this->tokens('tok1', 'tok2');
        $this->transport->on(RefreshApi::class, 'cached')
            ->respondWith(200, 'v1', [...self::TEXT, 'etag' => '"v1"'])
            ->respondWith(401)
            ->respondWith(304);
        $api->cached();

        $this->clock->advance(61);

        self::assertSame('v1', $api->cached());
        $resent = $this->transport->calls(RefreshApi::class, 'cached')[2]->request->headers;
        self::assertSame('"v1"', $resent['If-None-Match']);
        self::assertSame('Bearer tok2', $resent['Authorization']);
    }

    /**
     * @param class-string $interface
     */
    private function outbound(string $interface, ?Settings $settings = null): Outbound
    {
        $outbound = Outbound::create([$interface])
            ->withClock($this->clock)
            ->withTransport($this->transport)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'));

        return $settings instanceof Settings ? $outbound->withSettings($settings) : $outbound;
    }

    private function tokens(string ...$tokens): void
    {
        $expectation = $this->transport->onKey('refresh::token');
        foreach ($tokens as $token) {
            $expectation->respondWithJson(['access_token' => $token, 'expires_in' => 3600]);
        }
    }

    /**
     * @return list<mixed>
     */
    private function tokenCalls(Outbound $outbound): array
    {
        $name = $outbound->registry()->connectionNames()[0];

        return array_values(array_filter($this->transport->calls(), static fn ($call): bool => $name.'::token' === $call->endpoint));
    }
}
