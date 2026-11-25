<?php
declare(strict_types=1);

namespace Groza\Data;

use RuntimeException;

/** Стартовые данные: seed/data (подтверждённое) или seed/demo (примеры для разработки и тестов). */
final class Seeder
{
    private const ACTOR = 'seed';

    /**
     * Кладёт файлы из $seedDir, которых ещё нет в хранилище. Существующие не трогает:
     * после запуска источник правды — сервер. Вызывается при каждом запуске приложения.
     */
    public static function seedMissing(JsonStore $store, string $seedDir): void
    {
        self::seed($store, $seedDir, false);
    }

    /**
     * @param bool $overwrite заменить и существующие файлы (прежние попадают в историю версий)
     * @return list<string> имена записанных файлов
     */
    public static function seed(JsonStore $store, string $seedDir, bool $overwrite): array
    {
        $written = [];
        foreach (self::files($seedDir) as $name => $path) {
            if (!$overwrite && $store->updatedAt($name) !== null) {
                continue;
            }
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException("Seed file $name.json is not a JSON object");
            }
            try {
                $store->write($name, $data, self::ACTOR, $overwrite ? null : '');
            } catch (ConflictException) {
                continue; // файл успел появиться в параллельном запросе — он и главный
            }
            $written[] = $name;
        }

        return $written;
    }

    /**
     * Копирует файлы из $from в $to, если таких ещё нет (демо-PDF для документов в seed/demo).
     *
     * @return list<string> имена скопированных файлов
     */
    public static function copyMissingFiles(string $from, string $to): array
    {
        $copied = [];
        $files = glob(rtrim($from, '/\\') . '/*') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $path) {
            $target = rtrim($to, '/\\') . '/' . basename($path);
            if (!is_file($path) || is_file($target)) {
                continue;
            }
            if (!is_dir($to) && !mkdir($to, 0777, true) && !is_dir($to)) {
                throw new RuntimeException("Cannot create $to");
            }
            if (!copy($path, $target)) {
                throw new RuntimeException('Cannot copy ' . basename($path));
            }
            $copied[] = basename($path);
        }

        return $copied;
    }

    /** @return array<string,string> имя → путь, по алфавиту */
    private static function files(string $seedDir): array
    {
        $files = [];
        foreach (glob(rtrim($seedDir, '/\\') . '/*.json') ?: [] as $path) {
            $name = basename($path, '.json');
            if (preg_match('/^[a-z0-9-]+$/', $name) === 1) {
                $files[$name] = $path;
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }
}
