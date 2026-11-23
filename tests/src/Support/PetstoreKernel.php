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

namespace KonradMichalik\Outbound\Tests\Support;

use KonradMichalik\Outbound\Context\SapiExecutionContext;
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Transport\Transport;
use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi, UserApi};

/**
 * PetstoreKernel.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PetstoreKernel
{
    public const RESPONSES = __DIR__.'/../Fixtures/Petstore/Responses';

    public static function create(Transport $transport, FakeClock $clock, ?RecordingTagReporter $reporter = null, bool $web = true): Outbound
    {
        return Outbound::create([PetApi::class, StoreApi::class, UserApi::class])
            ->withTransport($transport)
            ->withClock($clock)
            ->withExecutionContext(new SapiExecutionContext($web))
            ->withCacheTagReporter($reporter ?? new RecordingTagReporter())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'));
    }
}
