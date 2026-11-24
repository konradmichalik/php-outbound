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

use KonradMichalik\Outbound\Exception\{ApiNotFound, ApiUnavailable};
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\CacheLayer;
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\{FakeClock, RecordingResponseCache, RecordingTagReporter};
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Resilience\DocumentApi;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

require_once __DIR__.'/../Fixtures/ResilienceDefinitions.php';

/**
 * CacheLayerStaleTest.
 *
 * `DocumentApi::doc` is cached for 60 seconds and may be served up to 300
 * seconds longer when the API fails.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CacheLayer::class)]
final class CacheLayerStaleTest extends TestCase
{
    private const TEXT = ['content-type' => 'text/plain'];

    private MockTransport $transport;

    private FakeClock $clock;

    private RecordingResponseCache $cache;

    private RecordingTagReporter $reporter;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->clock = new FakeClock();
        $this->cache = new RecordingResponseCache();
        $this->reporter = new RecordingTagReporter();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function failingStatuses(): iterable
    {
        yield 'internal server error' => [500];
        yield 'service unavailable' => [503];
        yield 'too many requests' => [429];
    }

    #[Test]
    #[DataProvider('failingStatuses')]
    public function anExpiredEntryIsServedWhenTheApiAnswersWithAServerError(int $status): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT)->respondWith($status);
        self::assertSame('v1', $api->doc(1));

        $this->clock->advance(61);

        self::assertSame('v1', $api->doc(1));
        self::assertSame(2, $this->transport->count(DocumentApi::class, 'doc'), 'The API was asked and failed.');
    }

    #[Test]
    public function aClientErrorIsNeverHiddenByAnExpiredEntry(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT)->respondWith(404);
        $api->doc(1);

        $this->clock->advance(61);

        $this->expectException(ApiNotFound::class);

        $api->doc(1);
    }

    #[Test]
    public function aServerErrorSurfacesOnceTheStaleWindowIsOver(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT)->respondWith(500);
        $api->doc(1);

        $this->clock->advance(61 + 300);

        $this->expectException(ApiUnavailable::class);

        $api->doc(1);
    }

    #[Test]
    public function theStaleWindowLastsForTheLifetimePlusTheStaleOnErrorSeconds(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT)->respondWith(500);
        $api->doc(1);

        $this->clock->advance(60 + 299);

        self::assertSame('v1', $api->doc(1), 'Still one second inside the window of 360 seconds.');
    }

    #[Test]
    public function theEtagOfAnUncachedReadGuardsTheNextWrite(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'fresh')->respondWith(200, 'x', [...self::TEXT, 'etag' => '"fresh"']);
        $this->transport->on(DocumentApi::class, 'save')->respondWith(204);

        $api->fresh(1);
        $api->save(1, ['a' => 1]);

        self::assertSame('"fresh"', $this->transport->calls(DocumentApi::class, 'save')[0]->request->headers['If-Match']);
    }

    #[Test]
    public function theBackendKeepsAnEntryForItsLifetimePlusTheStaleWindow(): void
    {
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', [...self::TEXT, 'etag' => '"v1"'])->respondWith(304);
        $api = $this->api();
        $api->doc(1);

        self::assertSame([360], $this->cache->lifetimes);

        $this->clock->advance(61);
        $api->doc(1);

        self::assertSame([360, 360], $this->cache->lifetimes, 'A revalidated entry is kept as long as a new one.');
    }

    #[Test]
    public function pagesAreToldHowLongTheirDataStaysFreshButOnlyForCachedSuccesses(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT);
        $this->transport->on(DocumentApi::class, 'flaky')->respondWith(200, 'uncached', self::TEXT);

        $api->doc(1);
        $api->flaky();

        self::assertCount(1, $this->reporter->reports, 'An uncached endpoint reports nothing.');
        self::assertSame(60, $this->reporter->reports[0]['lifetime']);
        self::assertContains('outbound_'.$this->connection(), $this->reporter->reports[0]['tags']);
    }

    #[Test]
    public function aFailedAnswerIsNotReportedToThePageCache(): void
    {
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(404);

        try {
            $this->api()->doc(1);
        } catch (ApiNotFound) {
        }

        self::assertSame([], $this->reporter->reports);
    }

    #[Test]
    public function theEtagOfAWriteAnswerIsNotRememberedForTheNextWrite(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'save')->respondWith(200, '', ['etag' => '"after-write"']);

        $api->save(1, ['a' => 1]);
        $api->save(1, ['a' => 2]);

        $writes = $this->transport->calls(DocumentApi::class, 'save');
        self::assertArrayNotHasKey('If-Match', $writes[1]->request->headers);
    }

    #[Test]
    public function theEtagOfAFailedReadIsNotRemembered(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(500, '', ['etag' => '"broken"']);
        $this->transport->on(DocumentApi::class, 'save')->respondWith(204);

        try {
            $api->doc(1);
        } catch (ApiUnavailable) {
        }
        $api->save(1, ['a' => 1]);

        self::assertArrayNotHasKey('If-Match', $this->transport->calls(DocumentApi::class, 'save')[0]->request->headers);
    }

    #[Test]
    public function aReadWithoutAnEtagLeavesTheNextWriteUnconditional(): void
    {
        $api = $this->api();
        $this->transport->on(DocumentApi::class, 'doc')->respondWith(200, 'v1', self::TEXT);
        $this->transport->on(DocumentApi::class, 'save')->respondWith(204);

        $api->doc(1);
        $api->save(1, ['a' => 1]);

        self::assertArrayNotHasKey('If-Match', $this->transport->calls(DocumentApi::class, 'save')[0]->request->headers);
    }

    private function connection(): string
    {
        return $this->outbound()->registry()->connectionNames()[0];
    }

    private function api(): DocumentApi
    {
        return $this->outbound()->api(DocumentApi::class);
    }

    private function outbound(): Outbound
    {
        return Outbound::create([DocumentApi::class])
            ->withClock($this->clock)
            ->withTransport($this->transport)
            ->withCache($this->cache)
            ->withCacheTagReporter($this->reporter)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'));
    }
}
