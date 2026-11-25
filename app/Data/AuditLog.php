<?php
declare(strict_types=1);

namespace Groza\Data;

final class AuditLog
{
    public function __construct(private string $file)
    {
    }

    public function record(string $actor, string $action, string $target): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create $dir");
        }
        $line = json_encode(
            ['actor' => $actor, 'action' => $action, 'target' => $target, 'at' => date('c')],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @return list<array{actor:string, action:string, target:string, at:string}> newest first */
    public function recent(int $limit = 50): array
    {
        $lines = is_file($this->file) ? file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        $result = [];
        foreach (array_reverse($lines ?: []) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $result[] = $row;
            }
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }
}
