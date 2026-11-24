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

namespace KonradMichalik\Outbound\Tests\Unit\Codegen;

use KonradMichalik\Outbound\Codegen\Names;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * NamesTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Names::class)]
final class NamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function studlyNames(): iterable
    {
        yield 'separators' => ['pet-store_item', 'PetStoreItem'];
        yield 'camel case boundary' => ['userId', 'UserId'];
        yield 'upper case is flattened' => ['HTTP', 'Http'];
        yield 'leading digits are stripped' => ['123abc', 'Abc'];
        yield 'leading digits before a separator' => ['1-pet-store', 'PetStore'];
        yield 'nothing usable' => ['---', 'Generated'];
        yield 'empty' => ['', 'Generated'];
    }

    #[Test]
    #[DataProvider('studlyNames')]
    public function studlyBuildsAValidClassName(string $value, string $expected): void
    {
        self::assertSame($expected, Names::studly($value));
    }

    #[Test]
    public function reservedClassNamesGetASuffix(): void
    {
        self::assertSame('ClassDto', Names::className('class'));
        self::assertSame('ArrayDto', Names::className('Array'));
        self::assertSame('Pet', Names::className('pet'));
    }

    #[Test]
    public function enumCasesAreNumberedOnCollisionAndPrefixedWhenNotStartingWithALetter(): void
    {
        self::assertSame(
            ['Active', 'Active2', 'Active3', 'Value1st', 'ClassValue'],
            Names::enumCases(['active', 'Active', 'ACTIVE', '1st', 'class']),
        );
    }

    #[Test]
    public function camelStartsLowerCase(): void
    {
        self::assertSame('userId', Names::camel('user_id'));
    }

    #[Test]
    public function propertiesAreCamelCasedOrKeptAsIdentifier(): void
    {
        self::assertSame('firstName', Names::property('first-name', true));
        self::assertSame('first_name', Names::property('first-name', false));
        self::assertSame('field1st', Names::property('1st', false));
        self::assertSame('field', Names::property('', false));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield 'ies' => ['categories', 'category'];
        yield 'ses' => ['statuses', 'status'];
        yield 's' => ['pets', 'pet'];
        yield 'ss stays' => ['class', 'class'];
        yield 'singular stays' => ['pet', 'pet'];
    }

    #[Test]
    #[DataProvider('plurals')]
    public function singularStripsThePluralEnding(string $plural, string $expected): void
    {
        self::assertSame($expected, Names::singular($plural));
    }
}
