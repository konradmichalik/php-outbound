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

use KonradMichalik\Outbound\Cache\{CacheEntry, InMemoryResponseCache, ResponseCache};

/**
 * RecordingResponseCache.
 *
 * Remembers how long the backend was asked to keep each entry.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RecordingResponseCache implements ResponseCache
{
    /** @var list<int> */
    public array $lifetimes = [];

    private readonly InMemoryResponseCache $inner;

    public function __construct()
    {
        $this->inner = new InMemoryResponseCache();
    }

    public function get(string $key): ?CacheEntry
    {
        return $this->inner->get($key);
    }

    public function set(string $key, CacheEntry $entry, int $lifetime, array $tags): void
    {
        $this->lifetimes[] = $lifetime;
        $this->inner->set($key, $entry, $lifetime, $tags);
    }

    public function flushTags(array $tags): void
    {
        $this->inner->flushTags($tags);
    }
}
