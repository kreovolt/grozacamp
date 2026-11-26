<?php
declare(strict_types=1);

/*
 * Уборка загруженных файлов, на которые нигде нет ссылок: ни в текущих данных, ни в хранимых версиях.
 * Админка сама заменённые файлы не удаляет, пока их версия хранится (иначе «Вернуть эту версию» дала бы битые ссылки);
 * когда версия уходит из последних 20, файл остаётся лишним — запускайте это время от времени.
 *
 *   php scripts/uploads-gc.php [--dry-run] [--min-age=СЕК] [--force]
 *
 *   --dry-run     только показать, что было бы удалено
 *   --min-age=N   не трогать файлы моложе N секунд (по умолчанию 3600: их мог только что загрузить чей-то запрос)
 *
 *   --force       не останавливаться на защитных проверках (см. ниже)
 *
 * Защита от «не той папки»: скрипт ничего не создаёт и останавливается с кодом 2, если в хранилище нет данных,
 * нет ни одной сохранённой версии или удалилось бы больше половины файлов загрузок — без --force.
 *
 * Хранилище и папка загрузок — из конфигурации (GROZA_STORAGE_DIR; uploadsDir, иначе по раскладке сайта — App::uploadsDir()).
 */

use Groza\App;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$config = require $root . '/config/config.php';

$opts = getopt('', ['dry-run', 'min-age:', 'force', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php scripts/uploads-gc.php [--dry-run] [--min-age=SECONDS] [--force]\n");
    exit(0);
}
$dry = isset($opts['dry-run']);
$minAge = isset($opts['min-age']) && ctype_digit((string) $opts['min-age']) ? (int) $opts['min-age'] : 3600;

$force = isset($opts['force']);

$app = App::boot($config, false); // только чтение: стартовые данные не подкладываем
if ($app->store()->publicNames() === []) {
    fwrite(STDERR, "uploads-gc: в хранилище {$config['storageDir']}/data нет данных сайта — похоже, указана не та папка (GROZA_STORAGE_DIR). Ничего не удалено.\n");
    exit(2);
}
$uploads = $app->uploads();
$orphans = $uploads->orphans($app->keptUploadRefs(), $minAge);
$total = count($uploads->inventory());
if (!$force && $orphans !== []) {
    if ($app->store()->versionCount() === 0) {
        fwrite(STDERR, "uploads-gc: в хранилище нет ни одной сохранённой версии — без истории не понять, что ещё нужно. Ничего не удалено (--force, если уверены).\n");
        exit(2);
    }
    if (count($orphans) * 2 > $total) {
        fwrite(STDERR, sprintf("uploads-gc: удалилось бы %d из %d файлов (больше половины) — похоже, указана не та папка. Ничего не удалено (--force, если уверены).\n", count($orphans), $total));
        exit(2);
    }
}

$failed = 0;
if (!$dry) {
    $swept = $uploads->sweepTemp($minAge);
    if ($swept > 0) {
        fwrite(STDOUT, "removed temp dirs: $swept\n");
    }
}
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
