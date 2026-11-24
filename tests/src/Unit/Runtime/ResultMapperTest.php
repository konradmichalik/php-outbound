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

use KonradMichalik\Outbound\Exception\{ApiConflict, ApiException, ApiMappingFailed, ApiNotFound, ApiRateLimited, ApiRedirected, ApiUnauthorized, ApiUnavailable, ApiValidationFailed};
use KonradMichalik\Outbound\Mapping\ValinorMapper;
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\ResultMapper;
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Result\{ResultApi, UserDto};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function count;
use function implode;
use function iterator_to_array;
use function json_encode;
use function str_repeat;
use function sys_get_temp_dir;

require_once __DIR__.'/../Fixtures/ResultDefinitions.php';

/**
 * ResultMapperTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ResultMapper::class)]
#[CoversClass(ValinorMapper::class)]
final class ResultMapperTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
    }

    #[Test]
    public function snakeCaseKeysAreMappedOntoTheDtoAndSuperfluousKeysAreIgnored(): void
    {
        $this->respondJson('user', ['first_name' => 'Ada', 'age' => 36, 'new_field' => true]);

        self::assertEquals(new UserDto('Ada', 36), $this->api()->user());
    }

    #[Test]
    public function listsAreMappedItemByItem(): void
    {
        $this->respondJson('users', [['first_name' => 'Ada', 'age' => 36], ['first_name' => 'Linus', 'age' => 55, 'nickname' => 'L']]);

        self::assertEquals([new UserDto('Ada', 36), new UserDto('Linus', 55, 'L')], $this->api()->users());
    }

    #[Test]
    public function thePayloadIsTakenFromTheUnwrapPath(): void
    {
        $this->respondJson('wrapped', ['data' => ['user' => ['first_name' => 'Ada', 'age' => 36]], 'meta' => []]);

        self::assertEquals(new UserDto('Ada', 36), $this->api()->wrapped());
    }

    #[Test]
    public function anEmptyBodyOrNullBecomesNullForANullableReturnType(): void
    {
        $this->transport->onKey('ResultApi::maybe')->respondWith(200, '');
        self::assertNull($this->api()->maybe());

        $this->transport->reset();
        $this->respondJson('maybe', null);
        self::assertNull($this->api()->maybe());
    }

    #[Test]
    public function aMismatchingResponseFailsWithoutQuotingTheValue(): void
    {
        $this->respondJson('user', ['first_name' => 'Ada', 'age' => 'secret-value']);

        try {
            $this->api()->user();
            self::fail('The mapping must fail.');
        } catch (ApiMappingFailed $exception) {
            self::assertSame(1794700110, $exception->getCode());
            self::assertStringContainsString('age', $exception->getMessage());
            self::assertStringNotContainsString('secret-value', $exception->getMessage());
        }
    }

    #[Test]
    public function aMissingRequiredValueFailsToo(): void
    {
        $this->respondJson('user', ['age' => 36]);

        $this->expectException(ApiMappingFailed::class);

        $this->api()->user();
    }

    #[Test]
    public function aBodyThatIsNotJsonFailsToMap(): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith(200, '{broken', ['content-type' => 'application/json']);

        $this->expectException(ApiMappingFailed::class);
        $this->expectExceptionCode(1794700140);

        $this->api()->user();
    }

    #[Test]
    public function bigIntegersSurviveAsStrings(): void
    {
        $this->transport->onKey('ResultApi::anything')->respondWith(200, '{"id":12345678901234567890}', ['content-type' => 'application/json']);

        self::assertSame(['id' => '12345678901234567890'], $this->api()->anything());
    }

    #[Test]
    public function aStringReturnTypeKeepsANonJsonBody(): void
    {
        $this->transport->onKey('ResultApi::text')->respondWith(200, 'plain words', ['content-type' => 'text/plain']);

        self::assertSame('plain words', $this->api()->text());
    }

    #[Test]
    public function voidDiscardsTheBody(): void
    {
        $this->transport->onKey('ResultApi::nothing')->respondWith(204);

        $this->api()->nothing();

        self::assertSame(1, $this->transport->count(ResultApi::class, 'nothing'));
    }

    #[Test]
    public function aRawResponseIsReadOnDemand(): void
    {
        $this->transport->onKey('ResultApi::raw')->respondWith(200, 'binary-ish', ['content-type' => 'application/octet-stream', 'x-file' => 'a']);

        $raw = $this->api()->raw();

        self::assertSame(200, $raw->status);
        self::assertSame('a', $raw->header('X-File'));
        self::assertSame('binary-ish', $raw->content());
        self::assertSame('binary-ish', implode('', iterator_to_array($raw->chunks(), false)));
    }

    #[Test]
    public function onStatusReplacesTheExceptionByTheDeclaredValue(): void
    {
        $this->transport->onKey('ResultApi::gone')->respondWith(404);
        self::assertNull($this->api()->gone());

        $this->transport->reset();
        $this->transport->onKey('ResultApi::gone')->respondWith(409);
        self::assertSame('conflict', $this->api()->gone());
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>, int}>
     */
    public static function errorStatuses(): iterable
    {
        yield '400' => [400, ApiValidationFailed::class, 1794700141];
        yield '422' => [422, ApiValidationFailed::class, 1794700141];
        yield '401' => [401, ApiUnauthorized::class, 1794700142];
        yield '403' => [403, ApiUnauthorized::class, 1794700142];
        yield '404' => [404, ApiNotFound::class, 1794700143];
        yield '410' => [410, ApiNotFound::class, 1794700143];
        yield '409' => [409, ApiConflict::class, 1794700144];
        yield '412' => [412, ApiConflict::class, 1794700144];
        yield '500' => [500, ApiUnavailable::class, 1794700146];
        yield '302' => [302, ApiRedirected::class, 1794700148];
        yield '418' => [418, ApiValidationFailed::class, 1794700147];
        yield '300' => [300, ApiRedirected::class, 1794700148];
        yield '399' => [399, ApiRedirected::class, 1794700148];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[Test]
    #[DataProvider('errorStatuses')]
    public function errorStatusesBecomeTypedExceptions(int $status, string $class, int $code): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith($status, '', ['x-request-id' => 'req-1']);

        try {
            $this->api()->user();
            self::fail('An error status must throw.');
        } catch (ApiException $exception) {
            self::assertInstanceOf($class, $exception);
            self::assertSame($code, $exception->getCode());
            self::assertSame($status, $exception->status);
            self::assertSame('ResultApi::user', $exception->endpoint);
            self::assertSame('req-1', $exception->requestId);
            self::assertStringContainsString('ResultApi::user answered '.$status, $exception->getMessage());
        }
    }

    #[Test]
    public function tooManyRequestsCarriesTheAnnouncedWait(): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith(429, '', ['retry-after' => '120']);

        try {
            $this->api()->user();
            self::fail('429 must throw.');
        } catch (ApiRateLimited $exception) {
            self::assertSame(120, $exception->retryAfterSeconds);
            self::assertSame(1794700145, $exception->getCode());
            self::assertSame(429, $exception->status);
        }
    }

    #[Test]
    public function validationErrorsExposeTheirFieldsAndTheTitleIsInTheMessage(): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith(422, (string) json_encode([
            'title' => "Invalid\nrequest",
            'detail' => 'Name is required',
            'errors' => ['name' => ['must not be blank']],
            'invalid-params' => [['name' => 'age', 'reason' => 'too low']],
        ]), ['content-type' => 'application/problem+json']);

        try {
            $this->api()->user();
            self::fail('422 must throw.');
        } catch (ApiValidationFailed $exception) {
            self::assertSame(['age' => ['too low'], 'name' => ['must not be blank']], $exception->fieldErrors);
            self::assertStringContainsString('Invalid request (Name is required)', $exception->getMessage());
        }
    }

    #[Test]
    public function aLongErrorTitleIsShortened(): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith(400, (string) json_encode(['title' => str_repeat('x', 500)]));

        try {
            $this->api()->user();
            self::fail('400 must throw.');
        } catch (ApiValidationFailed $exception) {
            self::assertStringEndsWith(': '.str_repeat('x', 200).'...', $exception->getMessage());
        }
    }

    #[Test]
    public function aBodyOfWhitespaceCountsAsEmpty(): void
    {
        $this->transport->onKey('ResultApi::maybe')->respondWith(200, " \n ", ['content-type' => 'application/json']);

        self::assertNull($this->api()->maybe());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function jsonStringResponses(): iterable
    {
        yield 'json content type' => [['content-type' => 'application/json']];
        yield 'upper case content type' => [['content-type' => 'Application/JSON; charset=utf-8']];
        yield 'no content type' => [[]];
    }

    /**
     * @param array<string, string> $headers
     */
    #[Test]
    #[DataProvider('jsonStringResponses')]
    public function aStringEndpointDecodesAJsonAnswerAndOtherwiseKeepsTheText(array $headers): void
    {
        $this->transport->onKey('ResultApi::text')->respondWith(200, '"hello"', $headers);

        self::assertSame('hello', $this->api()->text());
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function requestIds(): iterable
    {
        yield 'request-id' => [['request-id' => 'b'], 'b'];
        yield 'x-correlation-id' => [['x-correlation-id' => 'c'], 'c'];
        yield 'x-request-id wins' => [['x-request-id' => 'a', 'request-id' => 'b', 'x-correlation-id' => 'c'], 'a'];
        yield 'request-id beats correlation id' => [['request-id' => 'b', 'x-correlation-id' => 'c'], 'b'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[Test]
    #[DataProvider('requestIds')]
    public function theRequestIdIsTakenFromTheUsualHeaders(array $headers, string $expected): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith(500, '', $headers);

        try {
            $this->api()->user();
            self::fail('500 must throw.');
        } catch (ApiException $exception) {
            self::assertSame($expected, $exception->requestId);
        }
    }

    #[Test]
    public function titlesAreTrimmedAndKeptWholeUpToTwoHundredCharacters(): void
    {
        $exactly = str_repeat('y', 200);
        $this->transport->onKey('ResultApi::user')->respondWith(400, (string) json_encode(['title' => '  '.$exactly.'  ']));

        try {
            $this->api()->user();
            self::fail('400 must throw.');
        } catch (ApiValidationFailed $exception) {
            self::assertSame('ResultApi::user answered 400: '.$exactly, $exception->getMessage());
        }
    }

    #[Test]
    public function aLargeRawStreamIsDeliveredCompletely(): void
    {
        $this->transport->onKey('ResultApi::raw')->respondWith(200, str_repeat('z', 70_000), ['content-type' => 'application/octet-stream']);

        $chunks = [...$this->api()->raw()->chunks()];

        self::assertGreaterThan(1, count($chunks), 'The body is streamed, not returned in one piece.');
        self::assertSame(str_repeat('z', 70_000), implode('', $chunks));
    }

    #[Test]
    public function aRedirectNamesItsTargetWithoutSecrets(): void
    {
        $this->transport->onKey('ResultApi::user')->respondWith(301, '', ['location' => 'https://other.example.com/new']);

        $this->expectException(ApiRedirected::class);
        $this->expectExceptionMessage('redirects are not followed; it points to https://other.example.com/new');

        $this->api()->user();
    }

    /**
     * @param array<array-key, mixed>|null $data
     */
    private function respondJson(string $method, ?array $data): void
    {
        $this->transport->onKey('ResultApi::'.$method)->respondWithJson($data);
    }

    private function api(): ResultApi
    {
        return Outbound::create([ResultApi::class])
            ->withClock(new FakeClock())
            ->withTransport($this->transport)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api(ResultApi::class);
    }
}
