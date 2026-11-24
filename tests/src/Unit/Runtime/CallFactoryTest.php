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

use KonradMichalik\Outbound\Context\SapiExecutionContext;
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\{CallFactory, Settings};
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\RequestBuilder\{BuilderApi, LimitedApi};
use KonradMichalik\Outbound\Transport\TransportOptions;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

require_once __DIR__.'/../Fixtures/RequestBuilderDefinitions.php';

/**
 * CallFactoryTest.
 *
 * The transport options of a call: how large an answer may be and when TLS
 * verification may be switched off.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CallFactory::class)]
final class CallFactoryTest extends TestCase
{
    #[Test]
    public function theInstallationDefaultsApplyAndTlsIsVerified(): void
    {
        $options = $this->options(BuilderApi::class, new Settings(maxResponseBytes: 1234), new SapiExecutionContext());

        self::assertSame(1234, $options->maxResponseBytes);
        self::assertTrue($options->verifyTls);
        self::assertFalse($options->allowPrivateNetwork);
    }

    #[Test]
    public function aConnectionOverridesTheInstallationLimit(): void
    {
        $options = $this->options(LimitedApi::class, new Settings(maxResponseBytes: 1234), new SapiExecutionContext());

        self::assertSame(5, $options->maxResponseBytes);
    }

    #[Test]
    public function tlsVerificationCanOnlyBeSwitchedOffInDevelopment(): void
    {
        self::assertTrue($this->options(LimitedApi::class, new Settings(), new SapiExecutionContext(development: false))->verifyTls);
        self::assertFalse($this->options(LimitedApi::class, new Settings(), new SapiExecutionContext(development: true))->verifyTls);
    }

    #[Test]
    public function aConnectionThatAsksForVerificationAlwaysGetsIt(): void
    {
        self::assertTrue($this->options(BuilderApi::class, new Settings(), new SapiExecutionContext(development: true))->verifyTls);
    }

    /**
     * @param class-string<BuilderApi>|class-string<LimitedApi> $interface
     */
    private function options(string $interface, Settings $settings, SapiExecutionContext $execution): TransportOptions
    {
        $transport = new MockTransport();
        $transport->on($interface, 'ping')->respondWith(200, 'ok', ['content-type' => 'text/plain']);
        Outbound::create([$interface])
            ->withClock(new FakeClock())
            ->withSettings($settings)
            ->withExecutionContext($execution)
            ->withTransport($transport)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api($interface)
            ->ping();

        return $transport->calls()[0]->options;
    }
}
