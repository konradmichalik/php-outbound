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

namespace KonradMichalik\Outbound\Attribute\Auth;

use Attribute;
use KonradMichalik\Outbound\Value\ValueSource;

/**
 * TokenLogin.
 *
 * Posts the credentials as JSON to a login endpoint and sends the token from the
 * answer as bearer token, shared across all resources of the connection for ttl
 * seconds. A relative url is resolved against the base URI. Every body value
 * counts as sensitive.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TokenLogin implements Authentication
{
    /**
     * @param array<array-key, string|ValueSource> $body
     */
    public function __construct(
        public string|ValueSource $url,
        public array $body,
        public string $tokenField = 'token',
        public int $ttl = 3600,
    ) {}
}
