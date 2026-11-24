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

use KonradMichalik\Outbound\Exception\ApiNotFound;
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Proxy\FileProxyStore;
use KonradMichalik\Outbound\Runtime\Paginator;
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\FakeClock;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\ItemDto;
use KonradMichalik\Outbound\Tests\Unit\Fixtures\Pagination\PagesApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_map;
use function iterator_to_array;
use function ksort;
use function parse_str;
use function parse_url;
use function sys_get_temp_dir;

use const PHP_URL_QUERY;

require_once __DIR__.'/../Fixtures/PaginationDefinitions.php';

/**
 * PaginatorStyleTest.
 *
 * What each pagination style puts into the query of every request.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Paginator::class)]
final class PaginatorStyleTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
    }

    #[Test]
    public function offsetsAdvanceByTheNumberOfItemsReceived(): void
    {
        $this->transport->on(PagesApi::class, 'offset')->respondWithJson([['id' => 1], ['id' => 2]])->respondWithJson([['id' => 3]])->respondWithJson([]);

        self::assertSame([1, 2, 3], $this->ids($this->api()->offset()));
        self::assertSame([['limit' => '2', 'start' => '0'], ['limit' => '2', 'start' => '2'], ['limit' => '2', 'start' => '3']], $this->queries('offset'));
    }

    #[Test]
    public function pageNumbersStartWhereTheEndpointSaysAndUseTheirOwnNames(): void
    {
        $this->transport->on(PagesApi::class, 'zeroBased')->respondWithJson([['id' => 1]])->respondWithJson([['id' => 2]])->respondWithJson([]);

        $this->ids($this->api()->zeroBased());

        self::assertSame([['n' => '2', 'p' => '0'], ['n' => '2', 'p' => '1'], ['n' => '2', 'p' => '2']], $this->queries('zeroBased'));
    }

    #[Test]
    public function atMostMaxPagesAreRequested(): void
    {
        $this->transport->on(PagesApi::class, 'endless')->respondWithJson([['id' => 1]])->respondWithJson([['id' => 2]])->respondWithJson([['id' => 3]]);

        self::assertSame([1, 2], $this->ids($this->api()->endless()));
        self::assertSame(2, $this->transport->count(PagesApi::class, 'endless'));
    }

    #[Test]
    public function itemsAreReadFromTheConfiguredPathInsideTheDocument(): void
    {
        $this->transport->on(PagesApi::class, 'nested')->respondWithJson(['data' => ['rows' => [['id' => 7]]]])->respondWithJson(['data' => ['rows' => []]]);

        self::assertSame([7], $this->ids($this->api()->nested()));
    }

    #[Test]
    public function theCursorIsSentOnlyOnceTheServerHasGivenOne(): void
    {
        $this->transport->on(PagesApi::class, 'cursor')->respondWithJson(['items' => [['id' => 1]], 'next' => 'c2'])->respondWithJson(['items' => [['id' => 2]], 'next' => '']);

        self::assertSame([1, 2], $this->ids($this->api()->cursor()));
        self::assertSame([['size' => '2'], ['after' => 'c2', 'size' => '2']], $this->queries('cursor'));
    }

    #[Test]
    public function aNextLinkIsFollowedAsItIsWithoutAddingParameters(): void
    {
        $this->transport->on(PagesApi::class, 'linked')
            ->respondWithJson([['id' => 1]], 200, ['link' => '<https://pages.example.com/linked?after=1>; rel="next"'])
            ->respondWithJson([['id' => 2]]);

        self::assertSame([1, 2], $this->ids($this->api()->linked()));
        self::assertSame([['size' => '2'], ['after' => '1']], $this->queries('linked'));
    }

    #[Test]
    public function anErrorOnTheFirstPageSurfacesWithoutYieldingAnything(): void
    {
        $this->transport->on(PagesApi::class, 'offset')->respondWith(404);

        $this->expectException(ApiNotFound::class);

        $this->ids($this->api()->offset());
    }

    /**
     * @param iterable<int, ItemDto> $items
     *
     * @return list<int>
     */
    private function ids(iterable $items): array
    {
        return array_map(static fn (ItemDto $item): int => $item->id, iterator_to_array($items, false));
    }

    /**
     * @return list<array<int|string, array<mixed>|string>>
     */
    private function queries(string $method): array
    {
        return array_map(static function ($call): array {
            parse_str((string) parse_url($call->request->url, PHP_URL_QUERY), $query);
            ksort($query);

            return $query;
        }, $this->transport->calls(PagesApi::class, $method));
    }

    private function api(): PagesApi
    {
        return Outbound::create([PagesApi::class])
            ->withClock(new FakeClock())
            ->withTransport($this->transport)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/outbound-test-proxies'))
            ->api(PagesApi::class);
    }
}
