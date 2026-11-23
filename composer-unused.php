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

use ComposerUnused\ComposerUnused\Configuration\{Configuration, NamedFilter};

return static fn (Configuration $config): Configuration => $config
    // NoPrivateNetworkHttpClient needs IpUtils at runtime and only checks for it with class_exists().
    ->addNamedFilter(NamedFilter::fromString('symfony/http-foundation'));
