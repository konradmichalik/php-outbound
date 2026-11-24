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

namespace KonradMichalik\Outbound\Tests\Unit\Fixtures\RequestBuilder;

use DateTimeImmutable;
use KonradMichalik\Outbound\Attribute as Outbound;
use KonradMichalik\Outbound\Enum\{BodyFormat, QueryStyle};
use KonradMichalik\Outbound\Value\Env;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(
    baseUri: 'https://api.example.com/v1/',
    headers: ['X-Static' => 'one', 'X-Token' => new Env('OUTBOUND_RB_TOKEN_NEVER_SET', 'default-token')],
    dateFormat: 'Y-m-d',
)]
interface BuilderApi
{
    #[Outbound\Get('/items/{id}')]
    public function item(
        int $id,
        #[Outbound\Query] ?string $q = null,
        #[Outbound\Query('sort_by')] ?string $sort = null,
        #[Outbound\Header('X-Trace')] ?string $trace = null,
    ): string;

    #[Outbound\Get('/files/{name}')]
    public function file(string $name): string;

    /**
     * @param list<string> $tags
     */
    #[Outbound\Get('/tags')]
    public function tags(#[Outbound\Query(style: QueryStyle::CommaDelimited)] array $tags): string;

    #[Outbound\Get('/day')]
    public function day(#[Outbound\Query] DateTimeImmutable $date, #[Outbound\Query] bool $flag): string;

    /**
     * @param array<string, mixed> $data
     */
    #[Outbound\Post('/items')]
    public function create(#[Outbound\Body] array $data): string;

    /**
     * @param array<string, mixed> $credentials
     */
    #[Outbound\Post('/login')]
    public function login(#[Outbound\Body(BodyFormat::Form)] array $credentials): string;

    /**
     * @param array<string, mixed> $fields
     */
    #[Outbound\Post('/upload')]
    public function upload(#[Outbound\Multipart] array $fields): string;

    #[Outbound\Get('/secret')]
    public function secret(#[Outbound\Header('X-Api')] #[Outbound\Sensitive] string $key): string;

    #[Outbound\Get('/ping')]
    public function ping(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://limited.example.com', maxResponseBytes: 5, verifyTls: false)]
interface LimitedApi
{
    #[Outbound\Get('/ping')]
    public function ping(): string;
}
