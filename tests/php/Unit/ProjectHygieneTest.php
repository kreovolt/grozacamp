<?php
declare(strict_types=1);

namespace Groza\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Сквозные правила кода: strict_types везде, bootstrap/config не засоряют глобальную область. */
final class ProjectHygieneTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return list<string> */
    private static function phpFiles(): array
    {
        $files = [];
        foreach (['app', 'config', 'scripts', 'templates', 'public', 'tests/php'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() === 'php' && !str_contains($path, '/public/uploads/')) {
                    $files[] = $path;
                }
            }
        }
        sort($files);

        return $files;
    }

    public function test_every_php_file_declares_strict_types(): void
    {
        $missing = [];
        foreach (self::phpFiles() as $path) {
            $head = (string) file_get_contents($path, false, null, 0, 400);
            if (preg_match('/^<\?php\s+declare\(strict_types=1\);/', $head) !== 1) {
                $missing[] = substr($path, strlen(self::root()) + 1);
            }
        }
        $this->assertSame([], $missing, 'Нет declare(strict_types=1);');
    }

    public function test_config_file_does_not_leak_variables_into_the_includer(): void
    {
        $leaked = (static function (): array {
            $before = get_defined_vars();
            $config = require dirname(__DIR__, 3) . '/config/config.php';
            $after = get_defined_vars();
            unset($after['before'], $after['config']);

            return array_keys(array_diff_key($after, $before));
        })();
        $this->assertSame([], $leaked);
    }

    public function test_bootstrap_does_not_leak_variables_into_the_includer(): void
    {
        $storage = sys_get_temp_dir() . '/groza-boot-' . bin2hex(random_bytes(6));
        putenv('GROZA_STORAGE_DIR=' . $storage);
        putenv('GROZA_ENV=test');
        try {
            $leaked = (static function (): array {
                $before = get_defined_vars();
                $app = require dirname(__DIR__, 3) . '/app/bootstrap.php';
                $after = get_defined_vars();
                unset($after['before'], $after['app']);

                return array_keys(array_diff_key($after, $before));
            })();
        } finally {
            putenv('GROZA_STORAGE_DIR');
            putenv('GROZA_ENV');
            self::removeDir($storage);
        }
        $this->assertSame([], $leaked);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($dir);
    }
}
