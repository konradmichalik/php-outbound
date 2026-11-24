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

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * WarningLogger.
 *
 * Records the warnings it receives.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class WarningLogger extends AbstractLogger
{
    /** @var list<array<mixed>> */
    public array $contexts = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if ('warning' === $level) {
            $this->contexts[] = $context;
        }
    }
}
