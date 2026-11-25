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
            return $this->failOpen('нет каталога для счётчиков');
        }
        $now = $this->clock->now()->getTimestamp();
        $this->prune($now);

        $file = $this->file($bucket, $key);
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return $this->failOpen('не открыть файл счётчика');
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return $this->failOpen('не получить блокировку счётчика');
            }
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

    /** Лучше пропустить обращение, чем закрыть форму для всех; но сбой хранилища попадает в журнал ошибок (без адресов и ключей). */
    private function failOpen(string $why): bool
    {
        error_log('Groza rate limiter: ' . $why . ' — лимит не действует');

        return true;
    }

    /** Сколько обращений учтено за последние $windowSec секунд (без учёта нового). */
    public function count(string $bucket, string $key, int $windowSec): int
    {
        $fh = @fopen($this->file($bucket, $key), 'r');
        if ($fh === false) {
            return 0;
        }
        try {
            flock($fh, LOCK_SH); // не читать файл посреди записи hit()
            $data = json_decode((string) stream_get_contents($fh), true);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        $now = $this->clock->now()->getTimestamp();
        $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];

        return count(array_filter($hits, static fn ($t): bool => is_int($t) && $t > $now - $windowSec));
    }

    /** Обнулить счётчик пары «ведро + ключ». */
    public function reset(string $bucket, string $key): void
    {
        @unlink($this->file($bucket, $key));
    }

    private function file(string $bucket, string $key): string
    {
        return $this->dir . '/' . hash('sha256', $this->secret . $bucket . '|' . $key) . '.json';
    }

    private function prune(int $now): void
    {
        if ($this->pruneEvery > 1 && random_int(1, $this->pruneEvery) !== 1) {
            return;
        }
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            // Файл, который сейчас пишет hit(), не трогаем: читать его посреди записи значит принять живой счётчик за пустой
            $fh = @fopen($file, 'r');
            if ($fh === false) {
                continue;
            }
            $expired = false;
            if (flock($fh, LOCK_SH | LOCK_NB)) {
                $data = json_decode((string) stream_get_contents($fh), true);
                $expired = !is_array($data) || (int) ($data['expires'] ?? 0) < $now;
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            if ($expired) {
                @unlink($file);
            }
        }
    }
}
