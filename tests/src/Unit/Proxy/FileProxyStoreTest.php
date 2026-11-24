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

namespace KonradMichalik\Outbound\Tests\Unit\Proxy;

use KonradMichalik\Outbound\Proxy\FileProxyStore;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function chmod;
use function constant;
use function defined;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function glob;
use function is_dir;
use function mkdir;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function sys_get_temp_dir;
use function unlink;

/**
 * FileProxyStoreTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(FileProxyStore::class)]
final class FileProxyStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/outbound-store-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        foreach (scandir($this->directory) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                unlink($this->directory.'/'.$entry);
            }
        }
        rmdir($this->directory);
    }

    #[Test]
    public function aSavedProxyIsKnownAndStoredPrivately(): void
    {
        $store = new FileProxyStore($this->directory);

        self::assertFalse($store->has('Proxy_A'));

        $store->save('Proxy_A', '<?php // code');

        self::assertTrue($store->has('Proxy_A'));
        self::assertSame('<?php // code', file_get_contents($this->directory.'/Proxy_A.php'));
        self::assertSame(0o700, fileperms($this->directory) & 0o777);
        self::assertSame([$this->directory.'/Proxy_A.php'], glob($this->directory.'/*'), 'No temporary file is left behind.');
    }

    #[Test]
    public function loadingExecutesTheSavedCode(): void
    {
        $constant = 'OUTBOUND_STORE_LOADED_'.bin2hex(random_bytes(4));
        $store = new FileProxyStore($this->directory);
        $store->save('Proxy_B', "<?php define('{$constant}', 'yes');");

        self::assertFalse(defined($constant));

        $store->load('Proxy_B');

        self::assertSame('yes', constant($constant));
    }

    #[Test]
    public function codeIsNotLoadedFromADirectoryOthersCanWriteTo(): void
    {
        mkdir($this->directory, 0o700);
        file_put_contents($this->directory.'/Proxy_C.php', '<?php throw new LogicException("must not run");');
        chmod($this->directory, 0o777);
        $store = new FileProxyStore($this->directory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1794700164);

        $store->load('Proxy_C');
    }

    #[Test]
    public function anUncreatableDirectoryIsReported(): void
    {
        mkdir($this->directory, 0o700);
        file_put_contents($this->directory.'/blocker', '');
        $store = new FileProxyStore($this->directory.'/blocker/inner');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1794700163);

        set_error_handler(static fn (): bool => true);

        try {
            $store->save('Proxy_D', '<?php');
        } finally {
            restore_error_handler();
        }
    }
}
