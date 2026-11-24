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

use KonradMichalik\Outbound\Command\MakeDtoCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function count;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * MakeDtoCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(MakeDtoCommand::class)]
final class MakeDtoCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/outbound-make-dto-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (['/out/*', '/*'] as $pattern) {
            foreach (glob($this->directory.$pattern) ?: [] as $file) {
                is_dir($file) ? rmdir($file) : unlink($file);
            }
        }
        rmdir($this->directory);
    }

    #[Test]
    public function aSampleResponseBecomesDtoClassesInTheGivenNamespace(): void
    {
        $sample = $this->directory.'/contact.json';
        file_put_contents($sample, '{"first_name":"Ada","age":36,"address":{"city":"London"},"tags":["a","b"]}');
        $tester = new CommandTester(new MakeDtoCommand());

        $status = $tester->execute([
            'class' => 'ContactDto',
            '--from' => $sample,
            '--namespace' => 'Vendor\\Site\\Dto',
            '--output' => $this->directory.'/out',
            '--camel' => true,
        ]);

        self::assertSame(Command::SUCCESS, $status);
        $files = glob($this->directory.'/out/*.php') ?: [];
        self::assertGreaterThanOrEqual(2, count($files), 'The nested object gets a DTO of its own.');
        $root = (string) file_get_contents($this->directory.'/out/ContactDto.php');
        self::assertStringContainsString('namespace Vendor\\Site\\Dto;', $root);
        self::assertStringContainsString('$firstName', $root);
        self::assertStringNotContainsString('$first_name', $root);
        self::assertStringContainsString('list<string>', $root);
    }

    #[Test]
    public function withoutCamelTheKeysKeepTheirNames(): void
    {
        $sample = $this->directory.'/contact.json';
        file_put_contents($sample, '{"first_name":"Ada"}');

        (new CommandTester(new MakeDtoCommand()))->execute([
            'class' => 'ContactDto',
            '--from' => $sample,
            '--namespace' => 'Vendor\\Site\\Dto',
            '--output' => $this->directory.'/out',
        ]);

        self::assertStringContainsString('$first_name', (string) file_get_contents($this->directory.'/out/ContactDto.php'));
    }

    #[Test]
    public function aSecondRunSkipsExistingFilesUnlessForced(): void
    {
        $sample = $this->directory.'/contact.json';
        file_put_contents($sample, '{"first_name":"Ada"}');
        $arguments = ['class' => 'ContactDto', '--from' => $sample, '--namespace' => 'Vendor\\Site\\Dto', '--output' => $this->directory.'/out'];

        self::assertSame(Command::SUCCESS, (new CommandTester(new MakeDtoCommand()))->execute($arguments));
        self::assertSame(Command::FAILURE, (new CommandTester(new MakeDtoCommand()))->execute($arguments));
        self::assertSame(Command::SUCCESS, (new CommandTester(new MakeDtoCommand()))->execute($arguments + ['--force' => true]));
    }

    #[Test]
    public function aMissingOrInvalidSampleIsRejected(): void
    {
        $broken = $this->directory.'/broken.json';
        file_put_contents($broken, '{not json');

        foreach ([$this->directory.'/missing.json', $broken] as $from) {
            $tester = new CommandTester(new MakeDtoCommand());

            self::assertSame(Command::INVALID, $tester->execute(['class' => 'ContactDto', '--from' => $from, '--output' => $this->directory.'/out']));
            self::assertStringContainsString('--from must point to a readable JSON file.', $tester->getDisplay());
        }
    }
}
