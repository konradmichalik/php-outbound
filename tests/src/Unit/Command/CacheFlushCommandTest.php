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

namespace KonradMichalik\Outbound\Tests\Unit\Command;

use KonradMichalik\Outbound\Cache\CacheInvalidator;
use KonradMichalik\Outbound\Command\CacheFlushCommand;
use KonradMichalik\Outbound\Exception\InvalidDefinition;
use KonradMichalik\Outbound\Testing\MockTransport;
use KonradMichalik\Outbound\Tests\Support\{FakeClock, PetstoreKernel};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * CacheFlushCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CacheFlushCommand::class)]
final class CacheFlushCommandTest extends TestCase
{
    #[Test]
    public function theConnectionTagIsFlushed(): void
    {
        $registry = PetstoreKernel::create(new MockTransport(), new FakeClock())->registry();
        $connection = $registry->connectionNames()[0];
        $invalidator = new class implements CacheInvalidator {
            /** @var list<string> */
            public array $tags = [];

            public function invalidate(array $tags): void
            {
                $this->tags = [...$this->tags, ...$tags];
            }
        };
        $tester = new CommandTester(new CacheFlushCommand($registry, $invalidator));

        self::assertSame(Command::SUCCESS, $tester->execute(['connection' => $connection]));
        self::assertSame(['outbound_'.$connection], $invalidator->tags);
        self::assertStringContainsString('Flushed tag outbound_'.$connection.'.', $tester->getDisplay());
    }

    #[Test]
    public function anUnknownConnectionFlushesNothing(): void
    {
        $registry = PetstoreKernel::create(new MockTransport(), new FakeClock())->registry();
        $invalidator = new class implements CacheInvalidator {
            public int $calls = 0;

            public function invalidate(array $tags): void
            {
                ++$this->calls;
            }
        };
        $tester = new CommandTester(new CacheFlushCommand($registry, $invalidator));

        try {
            $tester->execute(['connection' => 'does-not-exist']);
            self::fail('An unknown connection must be rejected.');
        } catch (InvalidDefinition $exception) {
            self::assertSame(1794700026, $exception->getCode());
        }

        self::assertSame(0, $invalidator->calls);
    }
}
