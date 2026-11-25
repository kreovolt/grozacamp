<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Data;

use Groza\Data\AuditLog;
use PHPUnit\Framework\TestCase;

final class AuditLogTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/groza-audit-' . bin2hex(random_bytes(6)) . '/audit.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @rmdir(dirname($this->file));
    }

    public function test_audit_appends_jsonl(): void
    {
        $log = new AuditLog($this->file);
        $log->record('anna', 'shifts.status', 'abc');
        $first = $log->recent()[0];
        $this->assertSame(['actor', 'action', 'target', 'at'], array_keys($first));
        $this->assertSame('anna', $first['actor']);
        $this->assertSame('shifts.status', $first['action']);
        $this->assertSame('abc', $first['target']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $first['at']);
    }

    public function test_recent_is_newest_first_and_limited(): void
    {
        $log = new AuditLog($this->file);
        $this->assertSame([], $log->recent());
        for ($i = 1; $i <= 5; $i++) {
            $log->record('anna', 'a', (string) $i);
        }
        $this->assertSame(['5', '4'], array_column($log->recent(2), 'target'));
    }

    public function test_app_wires_store_and_audit_into_storage_dir(): void
    {
        $app = \Groza\Tests\Support\TestApp::make();
        $app->store()->write('settings', ['a' => 1], 'anna');
        $app->audit()->record('anna', 'x', 'y');
        $dir = $app->config()['storageDir'];
        $this->assertFileExists($dir . '/data/settings.json');
        $this->assertFileExists($dir . '/logs/audit.jsonl');
        $this->assertSame($app->store(), $app->store());
    }
}
