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

use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Resilience\InMemoryStateStore;
use KonradMichalik\Outbound\Runtime\{ConventionObserver, Dispatcher};
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\{FakeClock, WarningLogger};
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Result\ResultApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

require_once __DIR__.'/../Fixtures/ResultDefinitions.php';

/**
 * ConventionObserverTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ConventionObserver::class)]
#[CoversClass(Dispatcher::class)]
final class ConventionObserverTest extends TestCase
{
    private InMemoryStateStore $store;

    private WarningLogger $logger;

    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->logger = new WarningLogger();
        $this->store = new InMemoryStateStore();
        $this->transport = new MockTransport();
    }

    #[Test]
    public function aDeprecationIsLoggedOnceAndKeptForTheHealthCheck(): void
    {
        $this->transport->onKey('ResultApi::text')->respondWith(200, 'ok', [
            'content-type' => 'text/plain',
            'deprecation' => '@1700000000',
            'sunset' => 'Sat, 01 Jan 2028 00:00:00 GMT',
        ]);
        $api = $this->api();

        $api->text();
        $api->text();

        self::assertCount(1, $this->logger->contexts);
        self::assertSame('ResultApi::text', $this->logger->contexts[0]['endpoint']);
        self::assertSame('@1700000000', $this->logger->contexts[0]['deprecation']);
        self::assertSame('Sat, 01 Jan 2028 00:00:00 GMT', $this->logger->contexts[0]['sunset']);

        $stored = $this->store->get(ConventionObserver::deprecationKey('ResultApi::text'));
        self::assertNotNull($stored);
        self::assertSame('ResultApi::text', $stored['endpoint']);
        self::assertSame('result', $stored['connection']);
        self::assertSame('@1700000000', $stored['deprecation']);
        self::assertSame('Sat, 01 Jan 2028 00:00:00 GMT', $stored['sunset']);
    }

    #[Test]
    public function aMissingHeaderIsReportedAsDash(): void
    {
        $this->transport->onKey('ResultApi::text')->respondWith(200, 'ok', ['content-type' => 'text/plain', 'sunset' => 'Sat, 01 Jan 2028 00:00:00 GMT']);

        $this->api()->text();

        self::assertSame('-', $this->logger->contexts[0]['deprecation']);
        $stored = $this->store->get(ConventionObserver::deprecationKey('ResultApi::text'));
        self::assertNotNull($stored);
        self::assertNull($stored['deprecation']);
    }

    #[Test]
    public function aPlainResponseLeavesNoTrace(): void
    {
        $this->transport->onKey('ResultApi::text')->respondWith(200, 'ok', ['content-type' => 'text/plain']);

        $this->api()->text();

        self::assertSame([], $this->logger->contexts);
        self::assertNull($this->store->get(ConventionObserver::deprecationKey('ResultApi::text')));
    }

    #[Test]
    public function theStorageKeyDiffersPerEndpoint(): void
    {
        self::assertNotSame(ConventionObserver::deprecationKey('A::a'), ConventionObserver::deprecationKey('A::b'));
        self::assertSame(ConventionObserver::deprecationKey('A::a'), ConventionObserver::deprecationKey('A::a'));
    }

    private function api(): ResultApi
    {
        return Outbound::create([ResultApi::class])
            ->withClock(new FakeClock())
            ->withLogger($this->logger)
            ->withStateStore($this->store)
            ->withTransport($this->transport)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api(ResultApi::class);
    }
}
