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
#[Outbound\Connection(baseUri: 'https://search.example.com')]
interface QueryCommandApi
{
    #[Outbound\Get('/search')]
    public function search(#[Outbound\Query] string $q): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://contacts.example.com')]
interface ContactLookupApi
{
    #[Outbound\Get('/contacts/by-email/{email}')]
    public function byEmail(string $email, #[Outbound\Query] string $note): string;
}
