<?php
declare(strict_types=1);

/*
 * Уборка загруженных файлов, на которые нигде нет ссылок: ни в текущих данных, ни в хранимых версиях.
 * Админка сама заменённые файлы не удаляет, пока их версия хранится (иначе «Вернуть эту версию» дала бы битые ссылки);
 * когда версия уходит из последних 20, файл остаётся лишним — запускайте это время от времени.
 *
 *   php scripts/uploads-gc.php [--dry-run] [--min-age=СЕК]
 *
 *   --dry-run     только показать, что было бы удалено
 *   --min-age=N   не трогать файлы моложе N секунд (по умолчанию 3600: их мог только что загрузить чей-то запрос)
 *
 * Хранилище и папка загрузок — из конфигурации (GROZA_STORAGE_DIR, uploadsDir).
 */

use Groza\App;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$config = require $root . '/config/config.php';

$opts = getopt('', ['dry-run', 'min-age:', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php scripts/uploads-gc.php [--dry-run] [--min-age=SECONDS]\n");
    exit(0);
}
$dry = isset($opts['dry-run']);
$minAge = isset($opts['min-age']) && ctype_digit((string) $opts['min-age']) ? (int) $opts['min-age'] : 3600;

$app = App::boot($config);
$uploads = $app->uploads();
$orphans = $uploads->orphans($app->keptUploadRefs(), $minAge);

$failed = 0;
foreach ($orphans as $url) {
    if ($dry) {
        fwrite(STDOUT, "would delete $url\n");
        continue;
    }
    try {
        $uploads->delete($url);
        fwrite(STDOUT, "deleted $url\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "cannot delete $url: " . $e->getMessage() . "\n");
    }
}
fwrite(STDOUT, sprintf("uploads-gc: %s %d\n", $dry ? 'unreferenced files:' : 'removed:', $dry ? count($orphans) : count($orphans) - $failed));
exit($failed > 0 ? 1 : 0);
