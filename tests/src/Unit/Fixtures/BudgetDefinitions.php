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

namespace KonradMichalik\Outbound\Tests\Unit\Fixtures;

use KonradMichalik\Outbound\Attribute as Outbound;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://budget.example.com')]
#[Outbound\Auth\OAuth2ClientCredentials(tokenUrl: 'https://auth.budget.example.com/token', clientId: 'id', clientSecret: 'secret')]
interface TokenBudgetApi
{
    #[Outbound\Get('/items/{id}')]
    public function find(int $id): string;
}
