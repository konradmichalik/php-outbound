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

namespace KonradMichalik\Outbound\Tests\Unit\Mapping;

use DateTimeImmutable;
use KonradMichalik\Outbound\Mapping\Normalizer;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Normalizer\{Direction, Filter, Label, Priority, Reference};
use KonradMichalik\Outbound\Value\{Decimal, Iri};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/NormalizerDefinitions.php';

/**
 * NormalizerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Normalizer::class)]
final class NormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, mixed, mixed}>
     */
    public static function leaves(): iterable
    {
        $date = new DateTimeImmutable('2026-03-04T05:06:07+00:00');

        yield 'int' => [5, 5, '5'];
        yield 'float' => [1.5, 1.5, '1.5'];
        yield 'string' => ['text', 'text', 'text'];
        yield 'true' => [true, true, 'true'];
        yield 'false' => [false, false, 'false'];
        yield 'backed enum' => [Priority::High, 3, '3'];
        yield 'unit enum' => [Direction::Inbound, 'Inbound', 'Inbound'];
        yield 'date in the default format' => [$date, '2026-03-04T05:06:07+00:00', '2026-03-04T05:06:07+00:00'];
        yield 'decimal' => [new Decimal('12.50'), '12.50', '12.50'];
        yield 'iri' => [new Iri('/api/locations/42'), '/api/locations/42', '/api/locations/42'];
        yield 'stringable' => [new Label(), 'label-text', 'label-text'];
        yield 'json serializable' => [new Reference(), ['id' => 7, 'archived' => false], ['id' => '7', 'archived' => 'false']];
    }

    #[Test]
    #[DataProvider('leaves')]
    public function bodiesKeepTypesAndQueriesBecomeStrings(mixed $value, mixed $body, mixed $query): void
    {
        $normalizer = new Normalizer();

        self::assertSame($body, $normalizer->forBody($value));
        self::assertSame($query, $normalizer->forQuery($value));
    }

    #[Test]
    public function nullIsKeptInBodiesAndDroppedFromQueries(): void
    {
        $normalizer = new Normalizer();
        $filter = new Filter('pets', null, true);

        self::assertSame(['name' => 'pets', 'note' => null, 'active' => true, 'tags' => []], $normalizer->forBody($filter));
        self::assertSame(['name' => 'pets', 'active' => 'true'], $normalizer->forQuery($filter));
        self::assertNull($normalizer->forBody(null));
        self::assertNull($normalizer->forQuery(null));
    }

    #[Test]
    public function emptyListsAreDroppedFromQueriesOnly(): void
    {
        $normalizer = new Normalizer();

        self::assertSame(['a' => 1, 'empty' => []], $normalizer->forBody(['a' => 1, 'empty' => []]));
        self::assertSame(['a' => '1'], $normalizer->forQuery(['a' => 1, 'empty' => []]));
    }

    #[Test]
    public function nestedMembersAreNormalizedRecursively(): void
    {
        $normalizer = new Normalizer();
        $filter = new Filter('pets', 'x', false, [Priority::High, true, 2]);

        self::assertSame(
            ['name' => 'pets', 'note' => 'x', 'active' => false, 'tags' => [3, true, 2]],
            $normalizer->forBody($filter),
        );
        self::assertSame(
            ['name' => 'pets', 'note' => 'x', 'active' => 'false', 'tags' => ['3', 'true', '2']],
            $normalizer->forQuery($filter),
        );
    }

    #[Test]
    public function datesUseTheConfiguredFormat(): void
    {
        $normalizer = new Normalizer(dateFormat: 'Y-m-d');

        self::assertSame('2026-03-04', $normalizer->forBody(new DateTimeImmutable('2026-03-04T05:06:07+00:00')));
    }

    #[Test]
    public function queryBooleansCanBeNumeric(): void
    {
        $normalizer = new Normalizer(booleansAsStrings: false);

        self::assertSame(['on' => '1', 'off' => '0'], $normalizer->forQuery(['on' => true, 'off' => false]));
        self::assertSame(['on' => true, 'off' => false], $normalizer->forBody(['on' => true, 'off' => false]));
    }
}
