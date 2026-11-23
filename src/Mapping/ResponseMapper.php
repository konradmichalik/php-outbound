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

namespace KonradMichalik\Outbound\Mapping;

use KonradMichalik\Outbound\Attribute\MapKeys;
use KonradMichalik\Outbound\Metadata\ConnectionMetadata;

/**
 * ResponseMapper.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ResponseMapper
{
    /**
     * @throws \KonradMichalik\Outbound\Exception\ApiMappingFailed
     */
    public function map(string $signature, mixed $data, ConnectionMetadata $connection, ?MapKeys $mapKeys, string $endpoint): mixed;

    /**
     * Prepares the mapper for the given signatures, e.g. during cache warmup.
     */
    public function warmup(string ...$signatures): void;
}
