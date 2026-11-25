<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Http;

use Groza\Http\StaticFiles;
use PHPUnit\Framework\TestCase;

final class StaticFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/groza-static-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/public/assets', 0777, true);
        file_put_contents($this->root . '/public/assets/app.css', 'a{}');
        file_put_contents($this->root . '/public/evil.PHP', '<?php');
        file_put_contents($this->root . '/public/index.php', '<?php');
        file_put_contents($this->root . '/secret.txt', 'secret'); // рядом с public, не внутри
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($this->root);
    }

    public function test_serves_real_files_inside_the_site_root(): void
    {
        $this->assertTrue(StaticFiles::servable($this->root . '/public', '/assets/app.css'));
        $this->assertTrue(StaticFiles::servable($this->root . '/public', '/assets/../assets/app.css'));
    }

    public function test_refuses_escapes_scripts_directories_and_missing_files(): void
    {
        $public = $this->root . '/public';
        foreach (['/../secret.txt', '/assets/../../secret.txt', '/%2e%2e/secret.txt', '/..%2fsecret.txt', '/', '', '/assets', '/nope.css', '/index.php', '/evil.PHP', "/assets/app.css\0.png"] as $uri) {
            $this->assertFalse(StaticFiles::servable($public, $uri), $uri);
        }
    }
}
