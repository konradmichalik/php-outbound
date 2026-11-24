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

namespace KonradMichalik\Outbound\Tests\Unit\Runtime;

use KonradMichalik\Outbound\Runtime\Settings;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * SettingsTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Settings::class)]
final class SettingsTest extends TestCase
{
    #[Test]
    public function anEmptyConfigurationYieldsTheDefaults(): void
    {
        $settings = Settings::fromArray([]);

        self::assertSame(3.0, $settings->webTimeout);
        self::assertSame(30.0, $settings->cliTimeout);
        self::assertSame(8.0, $settings->webBudget);
        self::assertSame(1.0, $settings->webRetryAfterCap);
        self::assertSame(60.0, $settings->cliRetryAfterCap);
        self::assertSame(5_242_880, $settings->maxResponseBytes);
        self::assertEquals($settings, new Settings());
    }

    #[Test]
    public function numericValuesOverrideTheDefaults(): void
    {
        $settings = Settings::fromArray([
            'webTimeout' => '2.5',
            'cliTimeout' => 45,
            'webBudget' => '10',
            'webRetryAfterCap' => 0.5,
            'cliRetryAfterCap' => '120',
            'maxResponseBytes' => '1024',
        ]);

        self::assertSame(2.5, $settings->webTimeout);
        self::assertSame(45.0, $settings->cliTimeout);
        self::assertSame(10.0, $settings->webBudget);
        self::assertSame(0.5, $settings->webRetryAfterCap);
        self::assertSame(120.0, $settings->cliRetryAfterCap);
        self::assertSame(1024, $settings->maxResponseBytes);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableValues(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'not numeric' => ['fast'];
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    #[Test]
    #[DataProvider('unusableValues')]
    public function unusableValuesFallBackToTheDefault(mixed $value): void
    {
        $settings = Settings::fromArray(['webTimeout' => $value, 'maxResponseBytes' => $value]);

        self::assertSame(3.0, $settings->webTimeout);
        self::assertSame(5_242_880, $settings->maxResponseBytes);
    }
}
