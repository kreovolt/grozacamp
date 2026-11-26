<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Http;

use Groza\Http\ErrorPage;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Непойманный сбой — страница 500 с телефоном и WhatsApp и заголовками безопасности, без подробностей ошибки. */
final class ErrorPageTest extends TestCase
{
    private string $log;
    private string|false $oldLog;

    protected function setUp(): void
    {
        // причина пишется в журнал ошибок PHP: в тестах — во временный файл, а не в вывод
        $this->log = (string) tempnam(sys_get_temp_dir(), 'grz-err');
        $this->oldLog = ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->oldLog);
        @unlink($this->log);
    }

    public function test_corrupt_data_gives_friendly_500_with_contacts_and_security_headers(): void
    {
        $app = TestApp::make();
        file_put_contents($app->config()['storageDir'] . '/data/shifts.json', '{"items": [ испорчено');

        $res = TestApp::get($app, '/');

        $this->assertSame(500, $res->status());
        $body = $res->body();
        $this->assertStringContainsString('Что-то пошло не так', $body);
        $this->assertStringContainsString('href="tel:+73422474347"', $body);
        $this->assertStringContainsString('+7 (342) 247-43-47', $body);
        $this->assertStringContainsString('https://wa.me/79991239300', $body);
        $this->assertStringNotContainsString('JsonException', $body);
        $this->assertStringNotContainsString('.php', $body, 'ни путей, ни трассировки');
        $this->assertStringContainsString("default-src 'self'", (string) $res->header('Content-Security-Policy'));
        $this->assertSame('nosniff', $res->header('X-Content-Type-Options'));
        $this->assertStringContainsString('JsonException', (string) file_get_contents($this->log), 'причина — в журнале');
    }

    public function test_admin_failure_is_500_and_not_cached(): void
    {
        $app = TestApp::make();
        AdminHttp::user($app);
        AdminHttp::login($app);
        file_put_contents($app->config()['storageDir'] . '/data/shifts.json', 'not json');

        $res = AdminHttp::get($app, '/admin/shifts/');

        $this->assertSame(500, $res->status());
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        $this->assertSame('noindex, nofollow', $res->header('X-Robots-Tag'));
    }

    public function test_page_survives_missing_defaults_file(): void
    {
        $res = ErrorPage::response('/nonexistent/settings.php');

        $this->assertSame(500, $res->status());
        $this->assertStringContainsString('+7 (342) 247-43-47', $res->body());
    }

    public function test_bootstrap_turns_off_display_errors_on_the_site(): void
    {
        $root = dirname(__DIR__, 4);
        foreach (['prod' => '0', 'staging' => '0'] as $env => $expected) {
            $storage = sys_get_temp_dir() . '/groza-boot-' . bin2hex(random_bytes(6));
            $vars = array_filter(getenv(), static fn (string $k): bool => !str_starts_with($k, 'GROZA_'), ARRAY_FILTER_USE_KEY)
                + ['GROZA_ENV' => $env, 'GROZA_STORAGE_DIR' => $storage];
            $p = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-r', 'require "app/bootstrap.php"; echo ini_get("display_errors");'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $vars);
            $out = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            $this->assertSame($expected, trim($out), $env);
            if (is_dir($storage)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
                    $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
                }
                rmdir($storage);
            }
        }
    }
}
