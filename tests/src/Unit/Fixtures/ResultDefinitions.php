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

namespace KonradMichalik\Outbound\Tests\Unit\Fixtures\Result;

use KonradMichalik\Outbound\Attribute as Outbound;
use KonradMichalik\Outbound\Enum\KeyCase;
use KonradMichalik\Outbound\Value\RawResponse;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class UserDto
{
    public function __construct(
        public string $firstName,
        public int $age,
        public ?string $nickname = null,
    ) {}
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://results.example.com', keyCase: KeyCase::Camel)]
interface ResultApi
{
    #[Outbound\Get('/user')]
    public function user(): UserDto;

    #[Outbound\Get('/maybe')]
    public function maybe(): ?UserDto;

    /**
     * @return list<UserDto>
     */
    #[Outbound\Get('/users')]
    public function users(): array;

    #[Outbound\Get('/wrapped', unwrap: 'data.user')]
    public function wrapped(): UserDto;

    #[Outbound\Get('/gone')]
    #[Outbound\OnStatus(404, returns: null)]
    #[Outbound\OnStatus(409, returns: 'conflict')]
    public function gone(): mixed;

    #[Outbound\Get('/text')]
    public function text(): string;

    #[Outbound\Get('/anything')]
    public function anything(): mixed;

    #[Outbound\Post('/nothing')]
    public function nothing(): void;

    #[Outbound\Get('/raw')]
    public function raw(): RawResponse;
}
