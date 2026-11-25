<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\Controllers\BackupController;
use Groza\App;
use Groza\Http\Request;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Резервная копия: zip с data/*.json (без users и auth-meta) и uploads/**. «Сегодня» — 2027-01-15. */
final class BackupTest extends TestCase
{
    private App $app;
    private string $uploads;

    protected function setUp(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('нет модуля zip');
        }
        Session::reset();
        $this->uploads = sys_get_temp_dir() . '/groza-bk-up-' . bin2hex(random_bytes(6));
        mkdir($this->uploads . '/img', 0777, true);
        mkdir($this->uploads . '/docs', 0777, true);
        file_put_contents($this->uploads . '/img/abc-640.webp', 'WEBP-BYTES');
        file_put_contents($this->uploads . '/img/abc-1280.jpg', 'JPG-BYTES');
        file_put_contents($this->uploads . '/docs/0123456789abcdef.pdf', '%PDF-1.4 bytes');
        file_put_contents($this->uploads . '/.htaccess', 'deny');
        file_put_contents($this->uploads . '/docs/.hidden', 'x');
        $this->app = TestApp::make(['uploadsDir' => $this->uploads]);
        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
        if (!is_dir($this->uploads)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->uploads, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($this->uploads);
    }

    /** @return list<string> имена записей архива */
    private function entries(string $zipBytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bk');
        file_put_contents($tmp, $zipBytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'это настоящий zip');
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($tmp);
        sort($names);

        return $names;
    }

    public function test_backup_zip_contents(): void
    {
        $res = AdminHttp::get($this->app, '/admin/backup/');

        $this->assertSame(200, $res->status());
        $this->assertSame('application/zip', $res->header('Content-Type'));
        $this->assertSame('attachment; filename="groza-backup-2027-01-15.zip"', $res->header('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        $this->assertSame('nosniff', $res->header('X-Content-Type-Options'));

        $names = $this->entries($res->body());
        $this->assertContains('data/shifts.json', $names);
        foreach (['texts', 'settings', 'venues', 'team', 'documents', 'vacancies', 'faq', 'reviews'] as $n) {
            $this->assertContains("data/$n.json", $names);
        }
        $this->assertContains('uploads/img/abc-640.webp', $names);
        $this->assertContains('uploads/img/abc-1280.jpg', $names);
        $this->assertContains('uploads/docs/0123456789abcdef.pdf', $names);
        foreach ($names as $name) {
            $this->assertStringNotContainsString('users', $name);
            $this->assertStringNotContainsString('auth-meta', $name);
            $this->assertStringNotContainsString('..', $name);
            $this->assertStringNotContainsString('\\', $name);
            $this->assertStringNotContainsString(':', $name, 'без диска Windows');
            $this->assertStringStartsNotWith('/', $name);
            $this->assertMatchesRegularExpression('#^(data|uploads)/#', $name);
            $this->assertStringNotContainsString('/.', $name, 'скрытые файлы не берём');
        }
    }

    public function test_backup_has_real_data_and_no_password_hashes(): void
    {
        $res = AdminHttp::get($this->app, '/admin/backup/');
        $tmp = tempnam(sys_get_temp_dir(), 'bk');
        file_put_contents($tmp, $res->body());
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $this->assertSame((string) file_get_contents($this->app->config()['storageDir'] . '/data/shifts.json'), $zip->getFromName('data/shifts.json'));
        $this->assertSame('WEBP-BYTES', $zip->getFromName('uploads/img/abc-640.webp'));
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= (string) $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($tmp);
        $this->assertStringNotContainsString('$2y$', $all);
        $this->assertStringNotContainsString('argon2', $all);
        $this->assertStringNotContainsString('"hash"', $all);
    }

    public function test_backup_is_audited_and_temp_file_removed_after_send(): void
    {
        $res = AdminHttp::get($this->app, '/admin/backup/');
        $last = $this->app->audit()->recent(1)[0];
        $this->assertSame(['olga', 'backup.download'], [$last['actor'], $last['action']]);

        $tmpDir = $this->app->config()['storageDir'] . '/tmp';
        $this->assertCount(1, glob($tmpDir . '/*') ?: [], 'пока не отправлен — файл есть');
        ob_start();
        $res->send();
        $sent = (string) ob_get_clean();
        $this->assertSame((string) $res->header('Content-Length'), (string) strlen($sent));
        $this->assertSame(['data/shifts.json'], array_values(array_intersect(['data/shifts.json'], $this->entries($sent))));
        $this->assertSame([], glob($tmpDir . '/*') ?: [], 'после отправки временный файл убран');
    }

    public function test_backup_requires_login(): void
    {
        Session::reset();
        $fresh = TestApp::make(['uploadsDir' => $this->uploads]);
        $res = AdminHttp::get($fresh, '/admin/backup/');

        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/admin/login/', (string) $res->header('Location'));
        $this->assertNotSame('application/zip', $res->header('Content-Type'));
    }

    public function test_backup_is_get_only(): void
    {
        $this->assertNotSame(200, AdminHttp::postWithToken($this->app, '/admin/backup/')->status());
    }

    public function test_backup_without_zip_module_explains_what_to_do(): void
    {
        $res = (new BackupController($this->app, false))->download(Request::create('GET', '/admin/backup/'), []);

        $this->assertSame(503, $res->status());
        $this->assertStringContainsString('На сервере нет модуля zip', $res->body());
        $this->assertStringContainsString('скачайте данные через хостинг', $res->body());
        $this->assertSame([], glob($this->app->config()['storageDir'] . '/tmp/*') ?: []);
    }
}
