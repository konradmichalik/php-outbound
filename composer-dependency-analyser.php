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

use ShipMonk\ComposerDependencyAnalyser\Config\{Configuration, ErrorType};

return (new Configuration())
    // Fixture files hold several classes each and are loaded with require_once, not autoloaded.
    ->ignoreUnknownClassesRegex('~^KonradMichalik\\\\Outbound\\\\Tests\\\\Unit\\\\Fixtures\\\\~')
    // The test helpers in src/Testing are public API for the tests of integrations.
    ->ignoreErrorsOnPackageAndPath('phpunit/phpunit', __DIR__.'/src/Testing', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // NoPrivateNetworkHttpClient needs IpUtils at runtime and only checks for it with class_exists().
    ->ignoreErrorsOnPackage('symfony/http-foundation', [ErrorType::UNUSED_DEPENDENCY])
;
