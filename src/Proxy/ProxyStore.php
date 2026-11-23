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

namespace KonradMichalik\Outbound\Proxy;

/**
 * ProxyStore.
 *
 * Persists generated proxy code and loads it.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ProxyStore
{
    public function has(string $identifier): bool;

    public function save(string $identifier, string $code): void;

    public function load(string $identifier): void;
}
