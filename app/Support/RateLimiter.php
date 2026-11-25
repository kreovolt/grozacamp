<?php
declare(strict_types=1);

namespace Groza\Support;

/**
 * Лимит частоты на файлах: один файл на пару «ведро + ключ». Имя файла — sha256(секрет . ведро | ключ),
 * поэтому по каталогу нельзя узнать IP-адреса. Просроченные файлы удаляются попутно.
 */
final class RateLimiter
{
    /** @param int $pruneEvery раз в сколько обращений в среднем чистить каталог */
    public function __construct(
        private string $dir,
        private Clock $clock,
        private string $secret = '',
        private int $pruneEvery = 50,
    ) {
    }

    /** true — можно (обращение учтено), false — лимит исчерпан (обращение не учитывается). */
    public function hit(string $bucket, string $key, int $max, int $windowSec): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return true; // нет места для счётчиков — лучше пропустить, чем закрыть форму для всех
        }
        $now = $this->clock->now()->getTimestamp();
        $this->prune($now);

        $file = $this->dir . '/' . hash('sha256', $this->secret . $bucket . '|' . $key) . '.json';
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return true;
        }
        try {
            flock($fh, LOCK_EX);
            $data = json_decode((string) stream_get_contents($fh), true);
            $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
            $hits = array_values(array_filter($hits, static fn ($t): bool => is_int($t) && $t > $now - $windowSec));

            $allowed = count($hits) < $max;
            if ($allowed) {
                $hits[] = $now;
            }
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode(['expires' => $now + $windowSec, 'hits' => $hits], JSON_THROW_ON_ERROR));
            fflush($fh);

            return $allowed;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private function prune(int $now): void
    {
        if ($this->pruneEvery > 1 && random_int(1, $this->pruneEvery) !== 1) {
            return;
        }
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) @file_get_contents($file), true);
            if (!is_array($data) || (int) ($data['expires'] ?? 0) < $now) {
                @unlink($file);
            }
        }
    }
}
