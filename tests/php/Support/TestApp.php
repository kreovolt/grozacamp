<?php
declare(strict_types=1);

namespace Groza\Tests\Support;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;

final class TestApp
{
    /** @param array<string,mixed> $overrides */
    public static function make(array $overrides = []): App
    {
        $root = dirname(__DIR__, 3);
        $storage = sys_get_temp_dir() . '/groza-test-' . bin2hex(random_bytes(6));
        foreach (['data', 'versions', 'logs', 'ratelimit', 'mail'] as $dir) {
            mkdir($storage . '/' . $dir, 0777, true);
        }
        // Примерные данные появляются в Task 6
        foreach (glob($root . '/seed/demo/*.json') ?: [] as $file) {
            copy($file, $storage . '/data/' . basename($file));
        }
        register_shutdown_function(static fn () => self::removeDir($storage));

        $config = array_replace_recursive(require $root . '/config/config.php', [
            'env' => 'test',
            'storageDir' => $storage,
            'appSecret' => 'test-secret',
            'fakeNow' => '2027-01-15T12:00:00+05:00',
            // Тесты никогда не берут настоящие доступы из config/local.php или переменных окружения
            'mail' => ['host' => '', 'user' => '', 'pass' => ''],
            'staging' => ['user' => '', 'pass' => ''],
        ], $overrides);

        return App::boot($config);
    }

    /** @param array<string,string> $query */
    public static function get(App $app, string $path, array $query = []): Response
    {
        return $app->handle(Request::create('GET', $path, $query));
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,string> $headers
     */
    public static function post(App $app, string $path, array $post, array $headers = []): Response
    {
        return $app->handle(Request::create('POST', $path, [], $post, $headers));
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
