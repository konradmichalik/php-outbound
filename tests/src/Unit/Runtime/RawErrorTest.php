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

use KonradMichalik\Outbound\Exception\{ApiException, ApiTransportFailed};
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\Dispatcher;
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\{DownloadsApi, SmallDownloadsApi};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/RawDefinitions.php';

/**
 * RawErrorTest.
 *
 * A raw endpoint streams its body on success, but an error answer is read like any other.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Dispatcher::class)]
final class RawErrorTest extends TestCase
{
    #[Test]
    public function theErrorDetailOfARawEndpointReachesTheException(): void
    {
        $mock = new MockTransport();
        $mock->on(DownloadsApi::class, 'download')->respondWithJson(['title' => 'Not Found', 'detail' => 'No file 7'], 404, ['content-type' => 'application/problem+json']);
        $api = Outbound::create([DownloadsApi::class])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api(DownloadsApi::class);

        try {
            $api->download(7);
            self::fail('A 404 is no download.');
        } catch (ApiException $exception) {
            self::assertStringContainsString('No file 7', $exception->getMessage());
        }
    }

    #[Test]
    public function anOversizedErrorBodyStillYieldsTheStatusError(): void
    {
        $mock = new MockTransport();
        $mock->on(SmallDownloadsApi::class, 'download')->respondWith(503, str_repeat('x', 500));
        $api = Outbound::create([SmallDownloadsApi::class])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api(SmallDownloadsApi::class);

        try {
            $api->download(7);
            self::fail('A 503 is no download.');
        } catch (ApiException $exception) {
            self::assertNotInstanceOf(ApiTransportFailed::class, $exception);
            self::assertSame(503, $exception->status);
        }
    }
}
