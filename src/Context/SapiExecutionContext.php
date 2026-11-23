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

namespace KonradMichalik\Outbound\Context;

use const PHP_SAPI;

/**
 * SapiExecutionContext.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class SapiExecutionContext implements ExecutionContext
{
    public function __construct(
        private ?bool $web = null,
        private bool $development = false,
    ) {}

    public function isWeb(): bool
    {
        return $this->web ?? ('cli' !== PHP_SAPI && 'phpdbg' !== PHP_SAPI);
    }

    public function isDevelopment(): bool
    {
        return $this->development;
    }
}
