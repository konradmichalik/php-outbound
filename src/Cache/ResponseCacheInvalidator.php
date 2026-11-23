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

namespace KonradMichalik\Outbound\Cache;

/**
 * ResponseCacheInvalidator.
 *
 * Flushes the response cache only; used outside TYPO3.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ResponseCacheInvalidator implements CacheInvalidator
{
    public function __construct(
        private ResponseCache $cache,
    ) {}

    public function invalidate(array $tags): void
    {
        $this->cache->flushTags($tags);
    }
}
