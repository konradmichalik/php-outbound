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

use KonradMichalik\Outbound\Command\Writer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * WriterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Writer::class)]
final class WriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/outbound-writer-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (['Api/PetApi.php', 'Dto/Pet.php', 'Top.php'] as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }
        foreach (['Api', 'Dto', ''] as $directory) {
            if (is_dir($this->directory.'/'.$directory)) {
                rmdir($this->directory.'/'.$directory);
            }
        }
    }

    #[Test]
    public function withoutAnOutputDirectoryNothingIsWritten(): void
    {
        $output = new BufferedOutput();

        self::assertSame(Command::INVALID, Writer::write(['A' => 'x'], '', false, $output, static fn (string $name): string => $name.'.php'));
        self::assertStringContainsString('--output is required.', $output->fetch());
    }

    #[Test]
    public function filesAreWrittenIntoNestedDirectoriesThatAreCreatedOnDemand(): void
    {
        $output = new BufferedOutput();

        $status = Writer::write(
            ['Api/PetApi' => '<?php // api', 'Dto/Pet' => '<?php // dto'],
            $this->directory.'/',
            false,
            $output,
            static fn (string $name): string => $name.'.php',
        );

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame('<?php // api', file_get_contents($this->directory.'/Api/PetApi.php'));
        self::assertSame('<?php // dto', file_get_contents($this->directory.'/Dto/Pet.php'));
        self::assertStringContainsString('written '.$this->directory.'/Api/PetApi.php', $output->fetch());
    }

    #[Test]
    public function existingFilesAreKeptAndReportedUnlessForced(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory.'/Top.php', 'original');
        $path = static fn (string $name): string => $name.'.php';

        $kept = new BufferedOutput();
        $status = Writer::write(['Top' => 'generated'], $this->directory, false, $kept, $path);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame('original', file_get_contents($this->directory.'/Top.php'));
        self::assertStringContainsString('skipped '.$this->directory.'/Top.php (exists, use --force)', $kept->fetch());

        $status = Writer::write(['Top' => 'generated'], $this->directory, true, new BufferedOutput(), $path);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame('generated', file_get_contents($this->directory.'/Top.php'));
    }
}
