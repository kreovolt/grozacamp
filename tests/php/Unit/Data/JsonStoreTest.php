<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Data;

use Groza\Data\ConflictException;
use Groza\Data\JsonStore;
use PHPUnit\Framework\TestCase;

final class JsonStoreTest extends TestCase
{
    private string $root;
    private string $data;
    private string $versions;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/groza-store-' . bin2hex(random_bytes(6));
        $this->data = $this->root . '/data';
        $this->versions = $this->root . '/versions';
        mkdir($this->data, 0777, true);
        mkdir($this->versions, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    private function store(int $keep = 20): JsonStore
    {
        return new JsonStore($this->data, $this->versions, $keep);
    }

    public function test_roundtrip_keeps_cyrillic(): void
    {
        $s = $this->store();
        $s->write('settings', ['name' => 'Гроза'], 'anna');
        $this->assertSame(['name' => 'Гроза'], $s->read('settings'));
        $this->assertStringContainsString('Гроза', (string) file_get_contents($this->data . '/settings.json'));
    }

    public function test_read_missing_returns_default(): void
    {
        $this->assertSame(['items' => []], $this->store()->read('nope', ['items' => []]));
        $this->assertSame('', $this->store()->hash('nope'));
        $this->assertNull($this->store()->updatedAt('nope'));
    }

    public function test_rejects_bad_names(): void
    {
        foreach (['../x', 'Shifts'] as $bad) {
            try {
                $this->store()->write($bad, ['a' => 1], 'anna');
                $this->fail("accepted $bad");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_each_write_saves_previous_version(): void
    {
        $s = $this->store();
        $s->write('shifts', ['v' => 1], 'anna');
        $this->assertCount(0, $s->versions('shifts'));
        $s->write('shifts', ['v' => 2], 'boris');
        $v = $s->versions('shifts');
        $this->assertCount(1, $v);
        $this->assertSame('boris', $v[0]['actor']);
        $this->assertNotSame('', $v[0]['id']);
        $this->assertNotFalse(strtotime($v[0]['at']));
    }

    public function test_keeps_only_20_versions(): void
    {
        $s = $this->store();
        for ($i = 0; $i < 25; $i++) {
            $s->write('shifts', ['v' => $i], 'anna');
        }
        $v = $s->versions('shifts');
        $this->assertCount(20, $v);
        $ids = array_column($v, 'id');
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids, 'newest first');
        $this->assertCount(20, array_unique($ids));
    }

    public function test_restore_returns_old_content(): void
    {
        $s = $this->store();
        $s->write('shifts', ['v' => 1], 'anna');
        $s->write('shifts', ['v' => 2], 'anna');
        $id = $s->versions('shifts')[0]['id'];
        $s->restore('shifts', $id, 'boris');
        $this->assertSame(['v' => 1], $s->read('shifts'));
        $versions = $s->versions('shifts');
        $this->assertCount(2, $versions);
        $this->assertSame('boris', $versions[0]['actor']);
    }

    public function test_restore_rejects_unknown_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->store()->restore('shifts', '../../etc', 'anna');
    }

    public function test_stale_hash_throws_conflict(): void
    {
        $s = $this->store();
        $s->write('shifts', ['v' => 1], 'anna');
        $h = $s->hash('shifts');
        $s->write('shifts', ['v' => 2], 'boris', $h);
        try {
            $s->write('shifts', ['v' => 3], 'anna', $h);
            $this->fail('no conflict');
        } catch (ConflictException) {
            $this->assertSame(['v' => 2], $s->read('shifts'));
        }
    }

    public function test_no_temp_files_left(): void
    {
        $s = $this->store();
        $s->write('shifts', ['v' => 1], 'anna');
        $s->write('shifts', ['v' => 2], 'anna');
        $this->assertSame([], glob($this->data . '/*.tmp'));
    }
}
