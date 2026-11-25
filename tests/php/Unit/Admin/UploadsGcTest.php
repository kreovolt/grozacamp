<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Data\JsonStore;
use PHPUnit\Framework\TestCase;

/** scripts/uploads-gc.php: убирает файлы без ссылок, но не дадится «не той папке». */
final class UploadsGcTest extends TestCase
{
    private string $root;
    private string $uploads;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/groza-gc-' . bin2hex(random_bytes(6));
        $this->uploads = $this->root . '/uploads';
        mkdir($this->root . '/storage/data', 0777, true);
        mkdir($this->uploads . '/docs', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($this->root);
    }

    private function doc(string $name): void
    {
        file_put_contents($this->uploads . '/docs/' . $name, '%PDF-1.4');
    }

    /** Данные ссылаются на keep.pdf; $versions раз сохранены (версии появляются со второй записи). */
    private function data(int $versions): void
    {
        $store = new JsonStore($this->root . '/storage/data', $this->root . '/storage/versions');
        for ($i = 0; $i <= $versions; $i++) {
            $store->write('documents', ['items' => [['id' => 'd1', 'title' => 'x' . $i, 'file' => ['url' => '/uploads/docs/keep.pdf']]]], 'test');
        }
    }

    /** @param list<string> $args @return array{0:int,1:string,2:string} */
    private function gc(array $args = []): array
    {
        $env = array_merge(getenv(), [
            'GROZA_ENV' => 'test',
            'GROZA_STORAGE_DIR' => $this->root . '/storage',
            'GROZA_UPLOADS_DIR' => $this->uploads,
        ]);
        $p = proc_open(array_merge([PHP_BINARY, 'scripts/uploads-gc.php', '--min-age=0'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 4), $env);
        $this->assertIsResource($p);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);

        return [proc_close($p), $out, $err];
    }

    public function test_removes_unreferenced_files_and_keeps_referenced(): void
    {
        $this->data(2);
        $this->doc('keep.pdf');
        $this->doc('lost1.pdf');
        $this->doc('keep2.pdf'); // тоже лишний, но 2 из 3 — больше половины
        [$code] = $this->gc();
        $this->assertSame(2, $code, 'больше половины — стоп');
        $this->assertFileExists($this->uploads . '/docs/lost1.pdf');

        $this->doc('other-keep.pdf');
        unlink($this->uploads . '/docs/keep2.pdf');
        $this->data(0); // файл keep.pdf по-прежнему указан
        [$code, $out] = $this->gc(['--force']);
        $this->assertSame(0, $code, $out);
        $this->assertFileExists($this->uploads . '/docs/keep.pdf');
        $this->assertFileDoesNotExist($this->uploads . '/docs/lost1.pdf');
    }

    public function test_deletes_a_minority_of_orphans_without_force(): void
    {
        $this->data(2);
        foreach (['keep.pdf', 'b.pdf', 'c.pdf'] as $f) {
            $this->doc($f);
        }
        // b и c — на самом деле тоже на месте: подключаем их ссылками
        $store = new JsonStore($this->root . '/storage/data', $this->root . '/storage/versions');
        $store->write('documents', ['items' => [['file' => ['url' => '/uploads/docs/keep.pdf']], ['file' => ['url' => '/uploads/docs/b.pdf']]]], 'test');
        $this->doc('lost.pdf');

        [$code, $out] = $this->gc();

        $this->assertSame(0, $code, $out);
        $this->assertFileDoesNotExist($this->uploads . '/docs/lost.pdf');
        $this->assertFileExists($this->uploads . '/docs/b.pdf');
        $this->assertFileExists($this->uploads . '/docs/keep.pdf');
    }

    public function test_sweeps_stale_temp_dirs_of_interrupted_photo_processing(): void
    {
        $this->data(2);
        $this->doc('keep.pdf');
        mkdir($this->uploads . '/img/.tmp-abcd', 0777, true);
        file_put_contents($this->uploads . '/img/.tmp-abcd/abcd-480.webp', 'half');

        [$code, $out] = $this->gc(['--dry-run']);
        $this->assertSame(0, $code, $out);
        $this->assertDirectoryExists($this->uploads . '/img/.tmp-abcd', 'dry-run ничего не удаляет');

        [$code, $out] = $this->gc();
        $this->assertSame(0, $code, $out);
        clearstatcache();
        $this->assertDirectoryDoesNotExist($this->uploads . "/img/.tmp-abcd");
        $this->assertFileExists($this->uploads . '/docs/keep.pdf');
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->data(2);
        foreach (['keep.pdf', 'a.pdf', 'b.pdf', 'lost.pdf'] as $f) {
            $this->doc($f);
        }
        $store = new JsonStore($this->root . '/storage/data', $this->root . '/storage/versions');
        $store->write('documents', ['items' => [['file' => ['url' => '/uploads/docs/keep.pdf']], ['file' => ['url' => '/uploads/docs/a.pdf']], ['file' => ['url' => '/uploads/docs/b.pdf']]]], 'test');

        [$code, $out] = $this->gc(['--dry-run']);

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('would delete /uploads/docs/lost.pdf', $out);
        $this->assertFileExists($this->uploads . '/docs/lost.pdf');
    }

    public function test_empty_storage_aborts_and_seeds_nothing(): void
    {
        $this->doc('a.pdf');
        [$code, , $err] = $this->gc();

        $this->assertSame(2, $code);
        $this->assertStringContainsString('нет данных сайта', $err);
        $this->assertFileExists($this->uploads . '/docs/a.pdf');
        $this->assertSame([], glob($this->root . '/storage/data/*') ?: [], 'стартовые данные не подложены');
    }

    public function test_no_versions_at_all_aborts_unless_forced(): void
    {
        $this->data(0);
        $this->doc('keep.pdf');
        $this->doc('lost.pdf');
        $this->doc('lost2.pdf');
        $this->doc('lost3.pdf');

        [$code, , $err] = $this->gc();
        $this->assertSame(2, $code);
        $this->assertStringContainsString('нет ни одной сохранённой версии', $err);
        $this->assertFileExists($this->uploads . '/docs/lost.pdf');

        [$code] = $this->gc(['--force']);
        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($this->uploads . '/docs/lost.pdf');
        $this->assertFileExists($this->uploads . '/docs/keep.pdf');
    }
}
