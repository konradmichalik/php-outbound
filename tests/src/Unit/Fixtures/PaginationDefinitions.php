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

namespace KonradMichalik\Outbound\Tests\Unit\Fixtures\Pagination;

use KonradMichalik\Outbound\Attribute as Outbound;
use KonradMichalik\Outbound\Enum\PaginationStyle;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\ItemDto;

require_once __DIR__.'/Definitions.php';

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://pages.example.com')]
interface PagesApi
{
    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/offset')]
    #[Outbound\Paginate(style: PaginationStyle::Offset, size: 2, maxPages: 5, pageParam: 'start', sizeParam: 'limit')]
    public function offset(): iterable;

    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/zero')]
    #[Outbound\Paginate(style: PaginationStyle::Page, size: 2, maxPages: 5, pageParam: 'p', sizeParam: 'n', firstPage: 0)]
    public function zeroBased(): iterable;

    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/endless')]
    #[Outbound\Paginate(size: 1, maxPages: 2)]
    public function endless(): iterable;

    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/nested')]
    #[Outbound\Paginate(size: 2, itemsPath: 'data.rows')]
    public function nested(): iterable;

    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/cursor')]
    #[Outbound\Paginate(style: PaginationStyle::Cursor, size: 2, itemsPath: 'items', cursorPath: 'next', cursorParam: 'after')]
    public function cursor(): iterable;

    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/linked')]
    #[Outbound\Paginate(style: PaginationStyle::LinkHeader, size: 2)]
    public function linked(): iterable;
}
