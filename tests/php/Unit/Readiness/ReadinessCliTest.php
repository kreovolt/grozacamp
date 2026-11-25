<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Readiness;

use PHPUnit\Framework\TestCase;

/** scripts/readiness.php: печатает недостающее, код выхода 1, пока сайт не готов. */
final class ReadinessCliTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir() . '/groza-cli-' . bin2hex(random_bytes(6));
        mkdir($this->storage . '/data', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->storage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($this->storage);
    }

    private function fill(string $from): void
    {
        foreach (glob($from . '/*.json') ?: [] as $file) {
            copy($file, $this->storage . '/data/' . basename($file));
        }
    }

    /** @return array{0:int,1:string,2:string} код выхода, stdout, stderr */
    private function cli(): array
    {
        $env = array_merge(getenv(), [
            'GROZA_ENV' => 'test',
            'GROZA_STORAGE_DIR' => $this->storage,
            'GROZA_FAKE_NOW' => '2027-01-15T12:00:00+05:00',
        ]);
        $p = proc_open([PHP_BINARY, 'scripts/readiness.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 4), $env);
        $this->assertIsResource($p);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);

        return [proc_close($p), $out, $err];
    }

    public function test_cli_exit_codes(): void
    {
        $this->fill(dirname(__DIR__, 4) . '/seed/demo');
        [$code, $out] = $this->cli();
        $this->assertSame(1, $code, 'примерные данные — не готово');
        $this->assertMatchesRegularExpression('/^Не заполнено обязательных пунктов: \d+$/mu', $out);
        $this->assertMatchesRegularExpression('/^  [А-Яа-я][^\n]+ — [^\n]+$/mu', $out, 'раздел — пункт');
        $this->assertStringContainsString('/admin/', $out, 'и куда идти');
    }

    public function test_cli_without_data_exits_two_and_creates_nothing(): void
    {
        [$code, $out, $err] = $this->cli();

        $this->assertSame(2, $code);
        $this->assertStringContainsString('нет файлов', $err);
        $this->assertSame([], glob($this->storage . '/data/*') ?: [], 'проверка только читает: стартовые данные не подложены');
    }

    public function test_cli_complete_data_exits_zero(): void
    {
        $this->fill(dirname(__DIR__, 4) . '/tests/fixtures/complete');
        [$code, $out, $err] = $this->cli();

        $this->assertSame(0, $code, $out . $err);
        $this->assertStringContainsString('Готово к проверке', $out);
        $this->assertStringNotContainsString('Не заполнено', $out);
    }

    public function test_cli_prints_warnings_without_failing(): void
    {
        $this->fill(dirname(__DIR__, 4) . '/tests/fixtures/complete');
        $shifts = json_decode((string) file_get_contents($this->storage . '/data/shifts.json'), true);
        foreach ($shifts['items'] as $i => $s) {
            $shifts['items'][$i]['status'] = 'closed';
        }
        file_put_contents($this->storage . '/data/shifts.json', json_encode($shifts, JSON_UNESCAPED_UNICODE));

        [$code, $out] = $this->cli();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Предупреждения', $out);
        $this->assertStringContainsString('Нет предстоящих смен с открытым набором', $out);
    }
}
