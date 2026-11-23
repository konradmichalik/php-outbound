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

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withCache(__DIR__.'/.build/rector.cache')
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
;
