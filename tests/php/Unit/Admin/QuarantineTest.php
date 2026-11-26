<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\ImageProcessor;
use Groza\Admin\UploadException;
use Groza\Admin\Uploads;
use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * Удалённый или заменённый файл не остаётся на сайте (152-ФЗ: не тот скан), но откат версии его возвращает:
 * файл, нужный только истории, лежит в карантине storage/quarantine (вне корня сайта).
 */
final class QuarantineTest extends TestCase
{
    private App $app;
    private string $uploads;
    private string $quarantine;

    protected function setUp(): void
    {
        Session::reset();
        $this->uploads = sys_get_temp_dir() . '/groza-q-up-' . bin2hex(random_bytes(6));
        mkdir($this->uploads, 0777, true);
        $this->app = TestApp::make(['uploadsDir' => $this->uploads]);
        $this->quarantine = $this->app->quarantineDir();
        $this->app->setUploads(new Uploads(
            $this->uploads,
            '/uploads',
            new ImageProcessor(),
            isUploaded: static fn (string $f): bool => true,
            move: static fn (string $a, string $b): bool => copy($a, $b),
            quarantineDir: $this->quarantine,
        ));
        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
        foreach ([$this->uploads] as $dir) {
            if (is_dir($dir)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
                    $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
                }
                rmdir($dir);
            }
        }
    }

    /** @return array<string,mixed> */
    private function upload(string $fixture, string $name): array
    {
        $src = dirname(__DIR__, 3) . '/fixtures/uploads/' . $fixture;

        return ['name' => $name, 'type' => '', 'tmp_name' => $src, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($src)];
    }

    /** @param array<string,mixed> $post @param array<string,mixed> $files */
    private function save(string $collection, string $id, array $post, array $files = []): Response
    {
        return $this->app->handle(Request::create('POST', "/admin/$collection/$id/", [], ['_csrf' => Csrf::token(), '_hash' => $this->app->store()->hash($collection)] + $post, [], $files));
    }

    /** @return array<string,mixed> */
    private function doc(string $title): array
    {
        foreach ($this->app->store()->read('documents')['items'] as $d) {
            if ($d['title'] === $title) {
                return $d;
            }
        }
        $this->fail("нет документа $title");
    }

    private function public(string $url): string
    {
        return $this->uploads . substr($url, strlen('/uploads'));
    }

    private function quarantined(string $url): string
    {
        return $this->quarantine . substr($url, strlen('/uploads'));
    }

    private function restore(string $name, string $version): Response
    {
        return AdminHttp::postWithToken($this->app, '/admin/history/restore/', ['name' => $name, 'version' => $version, 'confirm' => '1']);
    }

    public function test_replaced_document_leaves_the_site_and_restore_brings_it_back(): void
    {
        $post = ['title' => 'Скан e2e', 'section' => 'inoe', 'date' => '2026-05-01', 'order' => ''];
        $this->assertSame(303, $this->save('documents', 'new', $post, ['file' => $this->upload('real.pdf', 'не тот скан.pdf')])->status());
        $doc = $this->doc('Скан e2e');
        $old = $doc['file']['url'];
        $this->assertFileExists($this->public($old));

        $this->assertSame(303, $this->save('documents', $doc['id'], $post, ['file' => $this->upload('real.pdf', 'верный скан.pdf')])->status());
        $new = $this->doc('Скан e2e')['file']['url'];
        $this->assertNotSame($old, $new);
        $this->assertFileDoesNotExist($this->public($old), 'заменённый файл по старому адресу больше не скачать');
        $this->assertFileExists($this->quarantined($old), 'но он нужен истории');
        $this->assertFileExists($this->public($new));

        // откат к версии со старым файлом: старый — снова на сайте, новый — в карантин (нынешнее состояние стало версией)
        $version = $this->app->store()->versions('documents')[0]['id'];
        $this->assertSame(303, $this->restore('documents', $version)->status());
        $this->assertSame($old, $this->doc('Скан e2e')['file']['url']);
        $this->assertFileExists($this->public($old));
        $this->assertFileDoesNotExist($this->quarantined($old));
        $this->assertFileDoesNotExist($this->public($new));
        $this->assertFileExists($this->quarantined($new));
    }

    public function test_deleted_record_file_goes_to_quarantine(): void
    {
        $post = ['title' => 'Удаляемый', 'section' => 'inoe', 'date' => '', 'order' => ''];
        $this->save('documents', 'new', $post, ['file' => $this->upload('real.pdf', 'x.pdf')]);
        $doc = $this->doc('Удаляемый');
        $url = $doc['file']['url'];

        $res = AdminHttp::postWithToken($this->app, '/admin/documents/' . $doc['id'] . '/delete/', ['confirm' => '1']);
        $this->assertSame(303, $res->status());
        $this->assertFileDoesNotExist($this->public($url));
        $this->assertFileExists($this->quarantined($url));
    }

    public function test_removed_gallery_photo_moves_with_all_its_sizes(): void
    {
        $venue = $this->app->store()->read('venues')['items'][0];
        $post = ['photos__newalt_1' => 'Новый корпус', 'confirm_readiness' => '1'];
        $this->save('venues', $venue['id'], $post + ['slug' => $venue['slug'], 'name' => $venue['name']], ['photos__new_1' => $this->upload('small.jpg', 'a.jpg')]);
        $photos = $this->app->store()->read('venues')['items'][0]['photos'];
        $src = end($photos)['src'];
        $files = glob($this->uploads . '/img/' . basename($src) . '-*') ?: [];
        $this->assertNotEmpty($files);

        $token = basename($src);
        $this->save('venues', $venue['id'], ['slug' => $venue['slug'], 'name' => $venue['name'], 'photos__del_' . $token => '1', 'confirm_readiness' => '1']);
        $this->assertSame([], glob($this->uploads . '/img/' . $token . '-*') ?: []);
        $this->assertCount(count($files), glob($this->quarantine . '/img/' . $token . '-*') ?: []);
    }

    public function test_file_still_shown_on_the_site_is_not_moved(): void
    {
        // один и тот же файл у двух записей: убрали из одной — вторая его показывает
        $post = ['title' => 'Первый', 'section' => 'inoe', 'date' => '', 'order' => ''];
        $this->save('documents', 'new', $post, ['file' => $this->upload('real.pdf', 'x.pdf')]);
        $first = $this->doc('Первый');
        $data = $this->app->store()->read('documents');
        $data['items'][] = ['id' => 'aaaaaaaaaaaa', 'title' => 'Второй', 'section' => 'inoe', 'file' => $first['file']];
        $this->app->store()->write('documents', $data, 'test');

        AdminHttp::postWithToken($this->app, '/admin/documents/' . $first['id'] . '/delete/', ['confirm' => '1']);
        $this->assertFileExists($this->public($first['file']['url']));
    }

    public function test_quarantine_refuses_paths_outside_uploads(): void
    {
        $u = $this->app->uploads();
        foreach (['/uploads/../storage/data/users.json', '/uploads/docs/../../x', '/etc/passwd', '/uploads/tmp/x.pdf', "/uploads/docs/a\0.pdf"] as $bad) {
            foreach (['quarantine', 'unquarantine', 'purgeQuarantined'] as $method) {
                try {
                    $u->$method($bad);
                    $this->fail("$method $bad");
                } catch (UploadException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_backup_includes_quarantine(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('нет zip');
        }
        $this->save('documents', 'new', ['title' => 'В копию', 'section' => 'inoe', 'date' => '', 'order' => ''], ['file' => $this->upload('real.pdf', 'x.pdf')]);
        $doc = $this->doc('В копию');
        AdminHttp::postWithToken($this->app, '/admin/documents/' . $doc['id'] . '/delete/', ['confirm' => '1']);
        $this->assertFileExists($this->quarantined($doc['file']['url']));

        $res = AdminHttp::get($this->app, '/admin/backup/');
        $this->assertSame(200, $res->status());
        $tmp = (string) tempnam(sys_get_temp_dir(), 'bk');
        file_put_contents($tmp, $res->body());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('quarantine/docs/' . basename($doc['file']['url'])));
        $zip->close();
        unlink($tmp);
    }
}
