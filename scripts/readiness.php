<?php
declare(strict_types=1);

/*
 * Готовность к проверке из консоли (то же, что на главной админки).
 *
 *   php scripts/readiness.php
 *
 * Печатает недостающие обязательные пункты («раздел — пункт») и предупреждения.
 * Код выхода: 0 — все обязательные пункты заполнены, 1 — нет, 2 — в хранилище нет данных сайта (скрипт только читает и сам ничего не создаёт).
 * Хранилище, окружение и «сегодня» берутся из конфигурации, как у сайта (в env=test часы — GROZA_FAKE_NOW).
 */

use Groza\App;
use Groza\Readiness\Readiness;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$config = require $root . '/config/config.php';

$app = App::boot($config, false); // только чтение: стартовые данные не подкладываем
$missing = array_values(array_filter(
    ['texts', 'shifts', 'venues', 'team', 'documents'],
    static fn (string $n): bool => $app->store()->updatedAt($n) === null
));
if ($missing !== []) {
    fwrite(STDERR, 'readiness: в хранилище ' . $config['storageDir'] . '/data нет файлов: ' . implode(', ', $missing) . ". Проверьте GROZA_STORAGE_DIR.\n");
    exit(2);
}
$items = Readiness::evaluate(Readiness::dataFrom($app), $app->clock()->now());

$failed = array_values(array_filter($items, static fn (array $i): bool => $i['level'] === 'required' && !$i['ok']));
$warnings = array_values(array_filter($items, static fn (array $i): bool => $i['level'] === 'warning' && !$i['ok']));
$base = rtrim((string) $config['baseUrl'], '/');

if ($failed === []) {
    fwrite(STDOUT, "Готово к проверке: все обязательные пункты заполнены.\n");
} else {
    fwrite(STDOUT, 'Не заполнено обязательных пунктов: ' . count($failed) . "\n");
    foreach ($failed as $i) {
        $section = Readiness::SECTIONS[$i['section']] ?? $i['section'];
        fwrite(STDOUT, '  ' . $section . ' — ' . $i['label'] . "\n    " . $base . $i['fix'] . "\n");
    }
}
if ($warnings !== []) {
    fwrite(STDOUT, "\nПредупреждения (запуск не блокируют): " . count($warnings) . "\n");
    foreach ($warnings as $i) {
        fwrite(STDOUT, '  ' . $i['label'] . "\n    " . $base . $i['fix'] . "\n");
    }
}

exit($failed === [] ? 0 : 1);
