<?php
declare(strict_types=1);

namespace Groza\Data;

use DateTimeImmutable;
use Groza\Support\Clock;
use Groza\Support\SystemClock;

final class JsonStore
{
    private const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    private const VERSION_ID = '/^\d{14}-\d{6}-[a-f0-9]{4}$/';

    /** Файлы с хешами паролей: версий не хранят, в историю, откат и резервную копию не попадают. */
    public const PRIVATE_NAMES = ['users', 'auth-meta'];

    /** @param list<string> $unversioned имена файлов, для которых предыдущие версии не сохраняются */
    public function __construct(
        private string $dataDir,
        private string $versionsDir,
        private int $keepVersions = 20,
        private array $unversioned = self::PRIVATE_NAMES,
        private ?Clock $clock = null,
    ) {
        $this->clock ??= new SystemClock(date_default_timezone_get());
    }

    /** Имена файлов данных, кроме закрытых (users, auth-meta): их версии бывают, они попадают в копию. @return list<string> */
    public function publicNames(): array
    {
        $names = [];
        foreach (glob($this->dataDir . '/*.json') ?: [] as $path) {
            $name = basename($path, '.json');
            if (preg_match('/^[a-z0-9_-]+$/D', $name) === 1 && !in_array($name, self::PRIVATE_NAMES, true)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /** Сколько версий хранится всего (у файлов, которые вообще ведут историю). */
    public function versionCount(): int
    {
        $n = 0;
        foreach ($this->publicNames() as $name) {
            $n += count(glob($this->versionDir($name) . '/*.json') ?: []);
        }

        return $n;
    }

    /**
     * Текст всех текущих файлов данных и всех хранимых версий (кроме закрытых): по ним понимают, какие загруженные файлы ещё нужны.
     *
     * @return \Generator<int,string>
     */
    public function snapshots(): \Generator
    {
        foreach ($this->publicNames() as $name) {
            $raw = @file_get_contents($this->file($name));
            if ($raw !== false) {
                yield $raw;
            }
            foreach (glob($this->versionDir($name) . '/*.json') ?: [] as $path) {
                $raw = @file_get_contents($path);
                if ($raw !== false) {
                    yield $raw;
                }
            }
        }
    }

    /** @param array<string,mixed> $default @return array<string,mixed> */
    public function read(string $name, array $default = []): array
    {
        $file = $this->file($name);
        $raw = is_file($file) ? file_get_contents($file) : false;
        if ($raw === false) {
            return $default;
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : $default;
    }

    public function hash(string $name): string
    {
        $file = $this->file($name);

        return is_file($file) ? (string) sha1_file($file) : '';
    }

    public function updatedAt(string $name): ?DateTimeImmutable
    {
        $file = $this->file($name);
        $time = is_file($file) ? filemtime($file) : false;
        if ($time === false) {
            return null;
        }

        return (new DateTimeImmutable('@' . $time))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    /** @param array<string,mixed> $data */
    public function write(string $name, array $data, string $actor, ?string $expectedHash = null): void
    {
        $file = $this->file($name);
        $json = json_encode($data, self::FLAGS) . "\n";

        $this->ensureDir($this->dataDir);
        $lock = fopen($file . '.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException("Cannot lock $name");
        }
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new \RuntimeException("Cannot lock $name");
        }
        try {
            $current = is_file($file) ? (string) sha1_file($file) : '';
            if ($expectedHash !== null && $expectedHash !== $current) {
                throw new ConflictException("Data '$name' was changed by someone else");
            }
            if ($current !== '' && !in_array($name, $this->unversioned, true)) {
                $this->saveVersion($name, $file, $actor);
            }
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
                @unlink($tmp);
                throw new \RuntimeException("Cannot write $name");
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return list<array{id:string, at:string, actor:string}> newest first */
    public function versions(string $name): array
    {
        $dir = $this->versionDir($name);
        $ids = [];
        foreach (glob($dir . '/*.json') ?: [] as $path) {
            $ids[] = basename($path, '.json');
        }
        rsort($ids, SORT_STRING);

        $result = [];
        foreach ($ids as $id) {
            $meta = json_decode((string) @file_get_contents($dir . '/' . $id . '.meta'), true);
            $meta = is_array($meta) ? $meta : [];
            $result[] = ['id' => $id, 'at' => (string) ($meta['at'] ?? ''), 'actor' => (string) ($meta['actor'] ?? '')];
        }

        return $result;
    }

    public function restore(string $name, string $versionId, string $actor): void
    {
        if (in_array($name, self::PRIVATE_NAMES, true)) {
            throw new \InvalidArgumentException("Data '$name' cannot be restored");
        }
        if (preg_match(self::VERSION_ID, $versionId) !== 1) {
            throw new \InvalidArgumentException('Bad version id');
        }
        $path = $this->versionDir($name) . '/' . $versionId . '.json';
        $raw = is_file($path) ? file_get_contents($path) : false;
        if ($raw === false) {
            throw new \InvalidArgumentException('Unknown version');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->write($name, is_array($data) ? $data : [], $actor);
    }

    private function saveVersion(string $name, string $file, string $actor): void
    {
        $dir = $this->versionDir($name);
        $this->ensureDir($dir);
        $newest = $this->versions($name)[0]['id'] ?? '';

        $id = $this->nextVersionId($newest);

        if (!copy($file, $dir . '/' . $id . '.json')) {
            throw new \RuntimeException("Cannot save version of $name");
        }
        $meta = json_encode(['actor' => $actor, 'at' => $this->clock->now()->format('c')], self::FLAGS);
        if (file_put_contents($dir . '/' . $id . '.meta', $meta) === false) {
            @unlink($dir . '/' . $id . '.json');
            throw new \RuntimeException("Cannot save version meta of $name");
        }

        foreach (array_slice($this->versions($name), $this->keepVersions) as $old) {
            @unlink($dir . '/' . $old['id'] . '.json');
            @unlink($dir . '/' . $old['id'] . '.meta');
        }
    }

    /** Идентификатор строго больше предыдущего, даже если часы стоят или ушли назад (без ожидания и бесконечных циклов). */
    private function nextVersionId(string $newest): string
    {
        $now = $this->clock->now();
        $id = $now->format('YmdHis-u') . '-' . bin2hex(random_bytes(2));
        if ($id > $newest) {
            return $id;
        }
        $base = DateTimeImmutable::createFromFormat('YmdHis-u', substr($newest, 0, 21), $now->getTimezone());
        if ($base === false) {
            throw new \RuntimeException('Bad version id on disk: ' . $newest);
        }

        return $base->modify('+1 microsecond')->format('YmdHis-u') . '-' . bin2hex(random_bytes(2));
    }

    private function file(string $name): string
    {
        return $this->dataDir . '/' . $this->checkName($name) . '.json';
    }

    private function versionDir(string $name): string
    {
        return $this->versionsDir . '/' . $this->checkName($name);
    }

    private function checkName(string $name): string
    {
        if (preg_match('/^[a-z0-9_-]+$/D', $name) !== 1) {
            throw new \InvalidArgumentException("Bad store name '$name'");
        }

        return $name;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create $dir");
        }
    }
}
