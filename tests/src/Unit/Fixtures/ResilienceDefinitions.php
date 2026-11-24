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

namespace KonradMichalik\Outbound\Tests\Unit\Fixtures\Resilience;

use KonradMichalik\Outbound\Attribute as Outbound;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://documents.example.com')]
#[Outbound\CircuitBreaker(failureThreshold: 2, cooldown: 600)]
interface DocumentApi
{
    #[Outbound\Get('/doc/{id}')]
    #[Outbound\Cache(lifetime: 60, staleOnError: 300)]
    public function doc(int $id): string;

    /**
     * @param array<string, mixed> $data
     */
    #[Outbound\Put('/doc/{id}', ifMatch: true)]
    public function save(int $id, #[Outbound\Body] array $data): void;

    #[Outbound\Get('/doc/{id}')]
    public function fresh(int $id): string;

    #[Outbound\Get('/flaky')]
    public function flaky(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://flaky.example.com')]
#[Outbound\Retry(maxRetries: 2)]
interface FlakyApi
{
    #[Outbound\Get('/x')]
    public function x(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://refresh.example.com')]
#[Outbound\Auth\OAuth2ClientCredentials(tokenUrl: 'https://auth.example.com/token', clientId: 'client', clientSecret: 'shh')]
interface RefreshApi
{
    #[Outbound\Get('/data')]
    public function data(): string;

    #[Outbound\Get('/cached')]
    #[Outbound\Cache(lifetime: 60)]
    public function cached(): string;
}
