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

namespace KonradMichalik\Outbound\Tests\Unit\Http;

use DateTimeImmutable;
use KonradMichalik\Outbound\Context\StaticCallContext;
use KonradMichalik\Outbound\Exception\BlockedRequest;
use KonradMichalik\Outbound\Http\{RequestBuilder, RequestSpec};
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\{CallFactory, EndpointInvoker};
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\RequestBuilder\BuilderApi;
use KonradMichalik\Outbound\Transport\{Call, Transport};
use KonradMichalik\Outbound\Value\FilePart;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function file_put_contents;
use function is_string;
use function parse_str;
use function parse_url;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_URL_QUERY;

require_once __DIR__.'/../Fixtures/RequestBuilderDefinitions.php';

/**
 * RequestBuilderTest.
 *
 * Calls an interface through the real runtime and inspects the request that
 * reaches the transport.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(RequestBuilder::class)]
#[CoversClass(CallFactory::class)]
#[CoversClass(EndpointInvoker::class)]
#[CoversClass(Outbound::class)]
final class RequestBuilderTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        foreach (['item', 'file', 'tags', 'day', 'create', 'login', 'upload', 'secret', 'ping'] as $method) {
            $this->transport->onKey('BuilderApi::'.$method)->respondWith(200, 'ok', ['content-type' => 'text/plain']);
        }
    }

    #[Test]
    public function theBaseUriLosesItsTrailingSlashAndThePathIsAppended(): void
    {
        $this->api()->ping();

        self::assertSame('GET', $this->request()->method);
        self::assertSame('https://api.example.com/v1/ping', $this->request()->url);
    }

    #[Test]
    public function queryParametersUseTheirWireNameAndNullsAreOmitted(): void
    {
        $this->api()->item(42, q: 'a b', sort: 'name');

        $url = $this->request()->url;
        self::assertStringStartsWith('https://api.example.com/v1/items/42?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame(['q' => 'a b', 'sort_by' => 'name'], $query);

        $this->api()->item(7);
        self::assertSame('https://api.example.com/v1/items/7', $this->request(1)->url);
    }

    #[Test]
    public function pathValuesCanNeverAddSegmentsOrAQuery(): void
    {
        $this->api()->file('a/b?c=d');

        self::assertSame('https://api.example.com/v1/files/a%2Fb%3Fc%3Dd', $this->request()->url);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function invalidPathValues(): iterable
    {
        yield 'empty' => ['', 1794700120];
        yield 'current directory' => ['.', 1794700123];
        yield 'parent directory' => ['..', 1794700123];
    }

    #[Test]
    #[DataProvider('invalidPathValues')]
    public function invalidPathValuesAreBlockedBeforeTheTransport(string $value, int $code): void
    {
        try {
            $this->api()->file($value);
            self::fail('The request must be blocked.');
        } catch (BlockedRequest $exception) {
            self::assertSame($code, $exception->getCode());
        }

        self::assertSame([], $this->transport->calls());
    }

    #[Test]
    public function listsFollowTheDeclaredQueryStyleAndValuesAreNormalized(): void
    {
        $api = $this->api();
        $api->tags(['a', 'b c']);
        $api->day(new DateTimeImmutable('2026-03-04T05:06:07+00:00'), true);

        self::assertStringEndsWith('/tags?tags=a%2Cb%20c', $this->request(0)->url);
        parse_str((string) parse_url($this->request(1)->url, PHP_URL_QUERY), $query);
        self::assertSame(['date' => '2026-03-04', 'flag' => 'true'], $query);
    }

    #[Test]
    public function staticHeadersEnvironmentDefaultsAndHeaderParametersAreSent(): void
    {
        $this->api()->item(1, trace: 'abc');

        $headers = $this->request()->headers;
        self::assertSame('application/json, application/problem+json;q=0.9', $headers['Accept']);
        self::assertSame('one', $headers['X-Static']);
        self::assertSame('default-token', $headers['X-Token']);
        self::assertSame('abc', $headers['X-Trace']);
        self::assertArrayNotHasKey('Content-Type', $headers);
    }

    #[Test]
    public function emptyHeaderParametersAreNotSent(): void
    {
        $this->api()->item(1, trace: '');

        self::assertArrayNotHasKey('X-Trace', $this->request()->headers);
    }

    #[Test]
    public function theLanguageOfTheContextIsSentWhenThereIsOne(): void
    {
        $this->api(new StaticCallContext(language: 'de'))->ping();
        $this->api()->ping();

        self::assertSame('de', $this->request(0)->headers['Accept-Language']);
        self::assertArrayNotHasKey('Accept-Language', $this->request(1)->headers);
    }

    #[Test]
    public function secretsAndSensitiveParametersAreMarkedForRedaction(): void
    {
        $this->api()->secret('key-value');

        $sensitive = $this->request()->sensitiveKeys;
        self::assertContains('X-Token', $sensitive);
        self::assertContains('X-Api', $sensitive);
        self::assertNotContains('X-Static', $sensitive);
    }

    #[Test]
    public function jsonBodiesKeepNumbersAndAreNotOverEscaped(): void
    {
        $this->api()->create(['name' => 'a/ä', 'price' => 1.0, 'tags' => []]);

        $request = $this->request();
        self::assertSame('POST', $request->method);
        self::assertSame('{"name":"a/ä","price":1.0,"tags":[]}', $request->body);
        self::assertSame('application/json', $request->headers['Content-Type']);
    }

    #[Test]
    public function formBodiesAreUrlEncoded(): void
    {
        $this->api()->login(['user' => 'a b', 'remember' => true]);

        $request = $this->request();
        self::assertSame('application/x-www-form-urlencoded', $request->headers['Content-Type']);
        self::assertIsString($request->body);
        parse_str($request->body, $form);
        self::assertSame(['user' => 'a b', 'remember' => 'true'], $form);
    }

    #[Test]
    public function multipartBodiesCarryFieldsAndFiles(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'outbound-rb');
        file_put_contents($path, 'from-disk');

        $recorder = new class implements Transport {
            public string $body = '';

            public string $contentType = '';

            public function request(Call $call): ResponseInterface
            {
                $this->contentType = $call->request->headers['Content-Type'] ?? '';
                $body = $call->request->body ?? [];
                foreach (is_string($body) ? [$body] : $body as $chunk) {
                    $this->body .= $chunk;
                }

                return (new MockHttpClient(new MockResponse('ok', ['response_headers' => ['content-type' => 'text/plain']])))->request('POST', 'https://api.example.com/');
            }
        };

        try {
            Outbound::create([BuilderApi::class])
                ->withClock(new FakeClock())
                ->withTransport($recorder)
                ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
                ->api(BuilderApi::class)
                ->upload([
                    'title' => 'Hi',
                    'memory' => FilePart::fromContents('in-memory', 'a.txt', 'text/plain'),
                    'disk' => FilePart::fromPath($path, 'b.txt'),
                ]);
        } finally {
            unlink($path);
        }

        $body = $recorder->body;
        self::assertStringStartsWith('multipart/form-data; boundary=', $recorder->contentType);
        self::assertStringContainsString('name="title"', $body);
        self::assertStringContainsString('Hi', $body);
        self::assertStringContainsString('filename="a.txt"', $body);
        self::assertStringContainsString('in-memory', $body);
        self::assertStringContainsString('filename="b.txt"', $body);
        self::assertStringContainsString('from-disk', $body);
    }

    #[Test]
    public function aMultipartFieldThatIsNeitherScalarNorFileIsBlocked(): void
    {
        $this->expectException(BlockedRequest::class);
        $this->expectExceptionCode(1794700122);

        $this->api()->upload(['bad' => new stdClass()]);
    }

    private function api(?StaticCallContext $context = null): BuilderApi
    {
        $outbound = Outbound::create([BuilderApi::class])
            ->withClock(new FakeClock())
            ->withTransport($this->transport)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'));

        return ($context instanceof StaticCallContext ? $outbound->withCallContext($context) : $outbound)->api(BuilderApi::class);
    }

    private function request(int $index = 0): RequestSpec
    {
        $call = $this->transport->calls()[$index] ?? null;
        self::assertNotNull($call, 'The call did not reach the transport.');

        return $call->request;
    }
}
