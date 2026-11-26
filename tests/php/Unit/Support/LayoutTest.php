<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Support;

use Groza\Support\Layout;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Папка загрузок следует раскладке сайта (как public/index.php): отдельная public/ или одна общая папка. */
final class LayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/groza-layout-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/bootstrap.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (['public/index.php', 'index.php', 'app/bootstrap.php'] as $f) {
            @unlink($this->root . '/' . $f);
        }
        @rmdir($this->root . '/public');
        @rmdir($this->root . '/app');
        @rmdir($this->root);
    }

    public function test_split_layout_keeps_uploads_inside_public(): void
    {
        mkdir($this->root . '/public');
        file_put_contents($this->root . '/public/index.php', '<?php');

        $this->assertTrue(Layout::isSplit($this->root));
        $this->assertSame($this->root . '/public', Layout::webRoot($this->root));
        $this->assertSame($this->root . '/public/uploads', Layout::uploadsDir($this->root));
    }

    public function test_single_folder_layout_puts_uploads_next_to_index(): void
    {
        file_put_contents($this->root . '/index.php', '<?php');

        $this->assertFalse(Layout::isSplit($this->root));
        $this->assertSame($this->root, Layout::webRoot($this->root));
        $this->assertSame($this->root . '/uploads', Layout::uploadsDir($this->root . '/'));
    }

    public function test_app_uses_layout_unless_config_overrides(): void
    {
        $repo = dirname(__DIR__, 4);
        $this->assertSame(Layout::uploadsDir($repo), TestApp::make()->uploadsDir());
        $this->assertSame(str_replace('\\', '/', $repo) . '/public/uploads', str_replace('\\', '/', TestApp::make()->uploadsDir()));
        $this->assertSame('/srv/x/uploads', TestApp::make(['uploadsDir' => '/srv/x/uploads/'])->uploadsDir());
    }

    public function test_no_code_hardcodes_public_uploads(): void
    {
        $hits = [];
        foreach (['app', 'scripts', 'config'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 4) . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (in_array($file->getExtension(), ['php', 'mjs'], true) && str_contains((string) file_get_contents($file->getPathname()), "/public/uploads'")) {
                    $hits[] = $file->getFilename();
                }
            }
        }
        $this->assertSame([], $hits, 'Папку загрузок даёт App::uploadsDir() / Layout::uploadsDir()');
    }
}
