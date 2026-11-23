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

use KonradMichalik\Outbound\Exception\ApiRedirected;
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\ResultMapper;
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\ItemsApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/Definitions.php';

/**
 * RedirectTest.
 *
 * Redirects are not followed, so a 3xx answer is a moved API, not invalid input.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ResultMapper::class)]
final class RedirectTest extends TestCase
{
    #[Test]
    public function aRedirectNamesItsTargetWithoutCredentials(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'find')->respondWith(301, '', ['location' => 'https://api.example.com/v2/items/1?api_key=s3cr3t']);
        $api = Outbound::create([ItemsApi::class])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api(ItemsApi::class);

        try {
            $api->find(1);
            self::fail('A redirect is no item.');
        } catch (ApiRedirected $exception) {
            self::assertSame(301, $exception->status);
            self::assertStringContainsString('https://api.example.com/v2/items/1', $exception->getMessage());
            self::assertStringNotContainsString('s3cr3t', $exception->getMessage());
        }
    }
}
