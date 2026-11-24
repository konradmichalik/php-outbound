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

namespace KonradMichalik\Outbound\Tests\Unit\Fixtures\Normalizer;

use JsonSerializable;
use Stringable;

enum Priority: int
{
    case High = 3;
}

enum Direction
{
    case Inbound;
}

final readonly class Reference implements JsonSerializable
{
    /**
     * @return array<string, int|bool>
     */
    public function jsonSerialize(): array
    {
        return ['id' => 7, 'archived' => false];
    }
}

final readonly class Label implements Stringable
{
    public function __toString(): string
    {
        return 'label-text';
    }
}

final readonly class Filter
{
    /**
     * @param list<mixed> $tags
     */
    public function __construct(
        public string $name,
        public ?string $note,
        public bool $active,
        public array $tags = [],
    ) {}
}
