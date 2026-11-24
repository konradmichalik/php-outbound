<?php

declare(strict_types=1);

/*
 * This file is part of the "php-outbound" Composer package.
 *
 * (c) Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use KonradMichalik\PhpCsFixerPreset\Config;
use KonradMichalik\PhpCsFixerPreset\Rules\Header;
use KonradMichalik\PhpCsFixerPreset\Rules\Set\RuleSet;
use Symfony\Component\Finder\Finder;

return Config::create()
    ->withRule(
        Header::fromComposer(),
    )
    // assertSame() on DTOs checks identity; the tests compare by value
    ->withRule(
        RuleSet::fromArray(['php_unit_strict' => false]),
    )
    ->withFinder(static fn (Finder $finder) => $finder->in(__DIR__)->exclude(['.build', 'vendor']))
;
