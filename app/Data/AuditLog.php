<?php
declare(strict_types=1);

namespace Groza\Data;

use Groza\Support\Clock;
use Groza\Support\SystemClock;

/**
 * Журнал действий (storage/logs/audit.jsonl, строка — JSON). Размер ограничен: файл больше $rotateBytes уходит
 * в audit.1.jsonl (прежний такой архив заменяется), так что на диске не больше двух файлов. recent() читает с конца
 * блоками (сначала текущий файл, потом архив) — память не зависит от размера журнала.
 */
final class AuditLog
{
    private const CHUNK = 65536;

    public function __construct(private string $file, private ?Clock $clock = null, private int $rotateBytes = 2097152)
    {
        $this->clock ??= new SystemClock(date_default_timezone_get());
    }

    public function record(string $actor, string $action, string $target): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create $dir");
        }
        $line = json_encode(
            ['actor' => $actor, 'action' => $action, 'target' => $target, 'at' => $this->clock->now()->format('c')],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        clearstatcache(true, $this->file);
        if (is_file($this->file) && (int) filesize($this->file) >= $this->rotateBytes) {
            @rename($this->file, $this->archive()); // два запроса разом: второй rename не найдёт файла — не беда
        }
        if (file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write audit log');
        }
    }

    /**
     * @param (callable(array<string,mixed>):bool)|null $accept оставить только подходящие записи; проверяется до подсчёта лимита
     * @return list<array{actor:string, action:string, target:string, at:string}> newest first
     */
    public function recent(int $limit = 50, ?callable $accept = null): array
    {
        $result = [];
        if ($limit <= 0) {
            return $result;
        }
        foreach ([$this->file, $this->archive()] as $file) {
            foreach (self::linesFromEnd($file) as $line) {
                $row = json_decode($line, true);
                if (is_array($row) && ($accept === null || $accept($row))) {
                    $result[] = $row;
                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }
        }

        return $result;
    }

    private function archive(): string
    {
        return (string) preg_replace('/(\.jsonl)?$/', '.1.jsonl', $this->file, 1);
    }

    /** Строки файла от последней к первой (пустые пропускаются), чтение блоками с конца. @return \Generator<int,string> */
    private static function linesFromEnd(string $file): \Generator
    {
        $h = is_file($file) ? @fopen($file, 'rb') : false;
        if ($h === false) {
            return;
        }
        try {
            fseek($h, 0, SEEK_END);
            $pos = (int) ftell($h);
            $rest = '';
            while ($pos > 0) {
                $read = min(self::CHUNK, $pos);
                $pos -= $read;
                fseek($h, $pos);
                $buf = (string) fread($h, $read) . $rest;
                $lines = explode("\n", $buf);
                $rest = (string) array_shift($lines); // начало первой строки блока может быть в предыдущем блоке
                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $line = rtrim($lines[$i], "\r");
                    if ($line !== '') {
                        yield $line;
                    }
                }
            }
            $rest = rtrim($rest, "\r");
            if ($rest !== '') {
                yield $rest;
            }
        } finally {
            fclose($h);
        }
    }
}
