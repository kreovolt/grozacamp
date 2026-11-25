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

    public function test_recent_can_filter_before_limiting(): void
    {
        $log = new AuditLog($this->file);
        $log->record('anna', 'keep', '1');
        for ($i = 0; $i < 10; $i++) {
            $log->record('anna', 'noise', (string) $i);
        }
        $log->record('anna', 'keep', '2');
        $rows = $log->recent(2, static fn (array $row): bool => $row['action'] === 'keep');
        $this->assertSame(['2', '1'], array_column($rows, 'target'));
    }

    public function test_timestamp_comes_from_the_injected_clock(): void
    {
        $log = new AuditLog($this->file, new \Groza\Support\FixedClock('2027-01-15T12:00:00+05:00'));
        $log->record('anna', 'a', 'b');
        $this->assertSame('2027-01-15T12:00:00+05:00', $log->recent()[0]['at']);
    }

    public function test_unwritable_log_is_reported_not_swallowed(): void
    {
        mkdir($this->file, 0777, true); // вместо файла — каталог: запись невозможна
        try {
            $this->expectException(\RuntimeException::class);
            @(new AuditLog($this->file))->record('anna', 'a', 'b');
        } finally {
            rmdir($this->file);
        }
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
