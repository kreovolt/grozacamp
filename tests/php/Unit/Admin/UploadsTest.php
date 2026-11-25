<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\ImageProcessor;
use Groza\Admin\UploadException;
use Groza\Admin\Uploads;
use Groza\Http\Request;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class UploadsTest extends TestCase
{
    private string $base;
    /** @var list<string> */
    private array $tmp = [];

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/groza-up-' . bin2hex(random_bytes(6));
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            is_file($f) && unlink($f);
        }
        $this->rm($this->base);
    }

    private function rm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($dir);
    }

    private function uploads(int $maxImg = 10485760, int $maxPdf = 20971520, ?ImageProcessor $p = null): Uploads
    {
        // Тестовые «загрузки»: файлы не приходили через HTTP, поэтому проверку is_uploaded_file и перенос подменяем
        return new Uploads($this->base, '/uploads', $p ?? new ImageProcessor(), $maxImg, $maxPdf, static fn (string $f): bool => true, static fn (string $a, string $b): bool => copy($a, $b));
    }

    /** @return array{name:string,type:string,tmp_name:string,error:int,size:int} */
    private function file(string $fixture, string $name = '', int $error = UPLOAD_ERR_OK): array
    {
        $src = dirname(__DIR__, 3) . '/fixtures/uploads/' . $fixture;

        return ['name' => $name !== '' ? $name : $fixture, 'type' => 'image/jpeg', 'tmp_name' => $src, 'error' => $error, 'size' => (int) filesize($src)];
    }

    private function bigFile(int $bytes, string $head, string $name): array
    {
        $p = sys_get_temp_dir() . '/groza-big-' . bin2hex(random_bytes(4));
        file_put_contents($p, $head . str_repeat('0', $bytes - strlen($head)));
        $this->tmp[] = $p;

        return ['name' => $name, 'type' => '', 'tmp_name' => $p, 'error' => 0, 'size' => $bytes];
    }

    private function assertRejected(callable $fn, string $contains): void
    {
        try {
            $fn();
            $this->fail('ожидалось UploadException');
        } catch (UploadException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }

    public function test_image_stored_under_img_with_random_name(): void
    {
        $d = $this->uploads()->storeImage($this->file('gps.jpg', 'IMG_0001.JPG'));
        $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $d['src']);
        $this->assertSame(['webp', 'jpg'], $d['formats']);
        foreach ($d['widths'] as $w) {
            foreach ($d['formats'] as $f) {
                $this->assertFileExists($this->base . '/img/' . basename($d['src']) . "-$w.$f");
            }
        }
    }

    public function test_double_extension_and_null_byte_names_are_harmless(): void
    {
        foreach (['photo.php.jpg', "evil.php\0.jpg"] as $name) {
            $d = $this->uploads()->storeImage($this->file('small.jpg', $name));
            $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $d['src']);
        }
        foreach (glob($this->base . '/img/*') as $f) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}-\d+\.(webp|jpg)$/', basename($f));
        }
    }

    public function test_php_disguised_as_jpg_rejected(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('fake.jpg')), 'Файл не похож на фото. Подходят JPEG, PNG и WebP');
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('fake.jpg', 'a.php.jpg')), 'Файл не похож на фото');
        $this->assertSame([], glob($this->base . '/img/*') ?: []);
    }

    public function test_disallowed_extension_rejected_even_with_image_content(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', 'shell.php')), 'Файл не похож на фото');
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', 'pic.svg')), 'Файл не похож на фото');
    }

    public function test_pdf_given_as_photo_and_photo_given_as_pdf_rejected(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('real.pdf', 'x.jpg')), 'Файл не похож на фото');
        $this->assertRejected(fn () => $this->uploads()->storePdf($this->file('small.jpg', 'x.pdf')), 'Это не PDF');
    }

    public function test_heic_rejected_with_hint(): void
    {
        try {
            $this->uploads()->storeImage($this->file('photo.heic', 'IMG_1.HEIC'));
            $this->fail();
        } catch (UploadException $e) {
            $this->assertStringContainsString('HEIC', $e->getMessage());
            $this->assertStringContainsString('JPEG', $e->getMessage());
        }
    }

    public function test_too_many_pixels_rejected(): void
    {
        $this->assertRejected(
            fn () => $this->uploads(p: new ImageProcessor(maxPixels: 10000))->storeImage($this->file('small.jpg')),
            'Фото слишком большое: уменьшите до'
        );
    }

    public function test_pixel_limit_follows_memory_limit_and_reports_it(): void
    {
        $this->assertSame(25000000, (new ImageProcessor(memoryLimit: -1))->effectiveMaxPixels());
        $this->assertSame(20132659, (new ImageProcessor(memoryLimit: 128 * 1048576))->effectiveMaxPixels());
        $this->assertSame(25000000, (new ImageProcessor(memoryLimit: 1024 * 1048576))->effectiveMaxPixels());
        // 3x2 «мегапикселя» не влезает в 40 МБ: (40-32)/5 МБ ≈ 1 Мп
        $this->assertRejected(
            fn () => $this->uploads(p: new ImageProcessor(memoryLimit: 40 * 1048576))->storeImage($this->file('bomb.png')),
            'Фото слишком большое: уменьшите до 1 мегапикселей'
        );
        $this->assertRejected(
            fn () => $this->uploads(p: new ImageProcessor(memoryLimit: 128 * 1048576))->storeImage($this->file('bomb.png')),
            'уменьшите до 20 мегапикселей'
        );
        $this->assertRejected(
            fn () => $this->uploads(p: new ImageProcessor(memoryLimit: -1))->storeImage($this->file('bomb.png')),
            'уменьшите до 25 мегапикселей'
        );
    }

    public function test_decompression_bomb_png_rejected_before_decoding_and_leaves_nothing(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('bomb.png')), 'Фото слишком большое');
        $this->assertSame([], glob($this->base . '/img/*') ?: []);
    }

    public function test_webp_input_accepted(): void
    {
        $d = $this->uploads()->storeImage($this->file('photo.webp', 'снимок.WEBP'));
        $this->assertSame(['webp', 'jpg'], $d['formats']);
        $this->assertSame([480, 600], $d['widths']);
        $this->assertFileExists($this->base . '/img/' . basename($d['src']) . '-600.webp');
        $this->assertFileExists($this->base . '/img/' . basename($d['src']) . '-600.jpg');
    }

    public function test_extension_mismatch_accepted_when_content_is_a_real_image(): void
    {
        $d = $this->uploads()->storeImage($this->file('photo.png', 'IMG_5.JPG'));
        $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $d['src']);
        foreach (['pic.gif', 'run.exe', 'noext', 'pic.jpg.exe'] as $name) {
            $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', $name)), 'Файл не похож на фото');
        }
    }

    public function test_non_utf8_names_do_not_bypass_extension_check(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storePdf($this->file('real.pdf', "Àvirus.exe")), 'Это не PDF');
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', "Àvirus.exe")), 'Файл не похож на фото');
        $this->assertRejected(fn () => $this->uploads()->storePdf($this->file('real.pdf', "ÀÁ")), 'Это не PDF');
        $r = $this->uploads()->storePdf($this->file('real.pdf', "Àdoc.pdf"));
        $this->assertTrue(mb_check_encoding($r['originalName'], 'UTF-8'));
        $this->assertStringEndsWith('doc.pdf', $r['originalName']);
        $this->assertSame(1, preg_match('#^/uploads/img/[0-9a-f]{16}$#', $this->uploads()->storeImage($this->file('small.jpg', "Àpic.jpg"))['src']));
    }

    public function test_rlo_and_zero_width_characters_stripped_from_original_name(): void
    {
        $r = $this->uploads()->storePdf($this->file('real.pdf', "Устав‮​ лагеря﻿.pdf"));
        $this->assertSame('Устав лагеря.pdf', $r['originalName']);
    }

    public function test_missing_php_extensions_give_friendly_error(): void
    {
        $p = new ImageProcessor(hasFunction: static fn (string $f): bool => $f !== 'imagewebp');
        $this->assertRejected(fn () => $this->uploads(p: $p)->storeImage($this->file('small.jpg')), 'Сервер не умеет обрабатывать фото. Сообщите разработчику.');
        $this->assertSame([], glob($this->base . '/img/*') ?: []);
    }

    public function test_image_size_limit(): void
    {
        $f = $this->bigFile(10485760 + 1, "\xFF\xD8\xFF\xE0", 'big.jpg');
        $this->assertRejected(fn () => $this->uploads()->storeImage($f), 'Фото больше 10 МБ');
    }

    public function test_pdf_magic_checked(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storePdf($this->file('fake.pdf')), 'Это не PDF');
    }

    public function test_pdf_size_limit(): void
    {
        $f = $this->bigFile(20971520 + 1, '%PDF-1.4', 'big.pdf');
        $this->assertRejected(fn () => $this->uploads()->storePdf($f), 'Файл больше 20 МБ');
    }

    public function test_cyrillic_name_kept_as_original(): void
    {
        $r = $this->uploads()->storePdf($this->file('real.pdf', 'Устав лагеря.pdf'));
        $this->assertSame('Устав лагеря.pdf', $r['originalName']);
        $this->assertMatchesRegularExpression('#^/uploads/docs/[0-9a-f]{16}\.pdf$#', $r['url']);
        $this->assertSame((int) filesize(dirname(__DIR__, 3) . '/fixtures/uploads/real.pdf'), $r['bytes']);
        $this->assertFileExists($this->base . '/docs/' . basename($r['url']));
    }

    public function test_original_name_sanitized(): void
    {
        $r = $this->uploads()->storePdf($this->file('real.pdf', "..\\..\\etc/Приказ\x00\x07 №201.pdf"));
        $this->assertSame('Приказ №201.pdf', $r['originalName']);
        $long = $this->uploads()->storePdf($this->file('real.pdf', str_repeat('я', 300) . '.pdf'));
        $this->assertSame(120, mb_strlen($long['originalName']));
    }

    public function test_upload_error_codes(): void
    {
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', '', UPLOAD_ERR_INI_SIZE)), 'Файл слишком большой для сервера');
        $this->assertRejected(fn () => $this->uploads()->storePdf($this->file('real.pdf', '', UPLOAD_ERR_FORM_SIZE)), 'Файл слишком большой для сервера');
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', '', UPLOAD_ERR_NO_FILE)), 'Файл не выбран');
        $this->assertRejected(fn () => $this->uploads()->storeImage($this->file('small.jpg', '', UPLOAD_ERR_PARTIAL)), 'не загрузился полностью');
    }

    public function test_production_check_refuses_files_that_were_not_uploaded(): void
    {
        $u = new Uploads($this->base, '/uploads', new ImageProcessor()); // без подмен: is_uploaded_file
        $this->assertRejected(fn () => $u->storeImage($this->file('small.jpg')), 'Не удалось загрузить файл');
        $this->assertRejected(fn () => $u->storePdf($this->file('real.pdf')), 'Не удалось загрузить файл');
    }

    public function test_delete_image_removes_all_variants(): void
    {
        $u = $this->uploads();
        $d = $u->storeImage($this->file('gps.jpg'));
        $other = $u->storeImage($this->file('small.jpg'));
        $u->delete($d['src']);
        $this->assertSame(count($other['widths']) * 2, count(glob($this->base . '/img/*')));
        $u->delete($d['src']); // повторное удаление — не ошибка
    }

    public function test_delete_pdf(): void
    {
        $u = $this->uploads();
        $r = $u->storePdf($this->file('real.pdf'));
        $u->delete($r['url']);
        $this->assertSame([], glob($this->base . '/docs/*') ?: []);
    }

    public function test_delete_outside_base_refused(): void
    {
        file_put_contents($this->base . '/../groza-keep.txt', 'x');
        $keep = realpath($this->base . '/../groza-keep.txt');
        $this->tmp[] = $keep;
        foreach (['/../config/config.php', '/uploads/../groza-keep.txt', '/uploads/img/../../groza-keep.txt', '/uploads\\..\\x', 'C:/Windows/win.ini', '/etc/passwd', '/uploads/img/a' . "\0" . '.jpg', 'uploads/img/x.jpg', '', '/uploads/', '/uploads/index.php'] as $url) {
            $this->assertRejected(fn () => $this->uploads()->delete($url), 'Нельзя удалить');
        }
        $this->assertFileExists($keep);
    }

    public function test_delete_refuses_symlink_escape(): void
    {
        $outside = sys_get_temp_dir() . '/groza-out-' . bin2hex(random_bytes(4));
        mkdir($outside);
        file_put_contents($outside . '/secret.pdf', '%PDF-');
        mkdir($this->base . '/docs');
        if (!@symlink($outside, $this->base . '/docs/link')) {
            unlink($outside . '/secret.pdf');
            rmdir($outside);
            $this->markTestSkipped('symlink недоступен в этой среде');
        }
        try {
            $this->assertRejected(fn () => $this->uploads()->delete('/uploads/docs/link/secret.pdf'), 'Нельзя удалить');
            $this->assertFileExists($outside . '/secret.pdf');
        } finally {
            unlink($this->base . '/docs/link');
            unlink($outside . '/secret.pdf');
            rmdir($outside);
        }
    }

    public function test_uploads_htaccess_blocks_scripts(): void
    {
        $h = file_get_contents(dirname(__DIR__, 4) . '/public/uploads/.htaccess');
        foreach (['php_flag engine off', 'RemoveHandler .php .phtml .phar', 'Options -ExecCGI -Indexes', 'jpg|jpeg|webp|png|pdf', 'X-Content-Type-Options', 'AddType image/webp .webp', 'AddType application/pdf .pdf'] as $needle) {
            $this->assertStringContainsString($needle, $h);
        }
    }

    public function test_oversized_admin_post_gets_413_not_419(): void
    {
        $app = TestApp::make(['postMaxBytes' => 1000]);
        $res = $app->handle(Request::create('POST', '/admin/photos/', [], [], ['Content-Length' => '5000']));
        $this->assertSame(413, $res->status());
        $this->assertStringContainsString('Файл слишком большой для сервера. Уменьшите фото (до 10 МБ) или PDF (до 20 МБ) и попробуйте снова.', $res->body());
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        // в пределах лимита — прежнее поведение (нет токена → 419); публичные адреса не затронуты
        $this->assertSame(419, $app->handle(Request::create('POST', '/admin/photos/', [], [], ['Content-Length' => '900']))->status());
        $this->assertNotSame(413, $app->handle(Request::create('POST', '/zayavka/', [], [], ['Content-Length' => '5000']))->status());
    }

    public function test_post_max_size_shorthand_parsed(): void
    {
        $this->assertSame(8 * 1048576, \Groza\App::iniBytes('8M'));
        $this->assertSame(1024, \Groza\App::iniBytes('1k'));
        $this->assertSame(2 * 1073741824, \Groza\App::iniBytes('2G'));
        $this->assertSame(0, \Groza\App::iniBytes('0'));
        $this->assertSame(5000, \Groza\App::iniBytes('5000'));
    }
}
