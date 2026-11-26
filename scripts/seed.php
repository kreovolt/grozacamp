<?php
declare(strict_types=1);

/*
 * Стартовые данные в хранилище.
 *
 *   php scripts/seed.php [--demo] [--target=DIR] [--force]
 *
 *   --demo        взять seed/demo (примеры для разработки и тестов) вместо seed/data
 *                 и положить демо-PDF из seed/files/demo в папку загрузок /docs (если их там нет)
 *   --target=DIR  папка хранилища (в ней data/ и versions/); по умолчанию — storageDir из конфигурации
 *   --force       заменить и существующие файлы (прежние останутся в истории версий)
 *
 * При env=prod --force и --demo запрещены: на боевом сайте источник правды — сервер.
 */

use Groza\Data\JsonStore;
use Groza\Data\Seeder;
use Groza\Support\Layout;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$config = require $root . '/config/config.php';

$opts = getopt('', ['demo', 'force', 'target:', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php scripts/seed.php [--demo] [--target=DIR] [--force]\n");
    exit(0);
}
$demo = isset($opts['demo']);
$force = isset($opts['force']);

if ($config['env'] === 'prod' && ($force || $demo)) {
    fwrite(STDERR, "seed: --force и --demo запрещены при env=prod (данные боевого сайта правятся только в админке). Без config/local.php сайт считается боевым: локально задайте GROZA_ENV=dev.\n");
    exit(1);
}

$target = is_string($opts['target'] ?? null) && $opts['target'] !== '' ? $opts['target'] : $config['storageDir'];
if (preg_match('#^([a-zA-Z]:)?[/\\\\]#', $target) !== 1) {
    $target = $root . '/' . $target;
}
$target = rtrim($target, '/\\');

$seedDir = $root . '/seed/' . ($demo ? 'demo' : 'data');
$store = new JsonStore($target . '/data', $target . '/versions');

try {
    $written = Seeder::seed($store, $seedDir, $force);
    $copied = $demo ? Seeder::copyMissingFiles($root . '/seed/files/demo', ((string) ($config['uploadsDir'] ?? '') ?: Layout::uploadsDir($root)) . '/docs') : [];
} catch (Throwable $e) {
    fwrite(STDERR, 'seed: ' . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "seed: %s → %s/data: %s\n",
    $demo ? 'seed/demo' : 'seed/data',
    $target,
    $written === [] ? 'всё уже на месте' : implode(', ', $written)
));
if ($copied !== []) {
    fwrite(STDOUT, 'seed: uploads/docs: ' . implode(', ', $copied) . "\n");
}
exit(0);
