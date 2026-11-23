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

use KonradMichalik\Outbound\Context\CallContext;

/**
 * SwitchableCallContext.
 *
 * A context whose site a test can change between calls.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class SwitchableCallContext implements CallContext
{
    public function __construct(
        public ?string $site = null,
    ) {}

    public function scope(): ?string
    {
        return $this->site;
    }

    public function setting(string $key): mixed
    {
        return null;
    }

    public function language(): ?string
    {
        return null;
    }
}
