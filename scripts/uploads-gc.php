<?php
declare(strict_types=1);

/*
 * Уборка загруженных файлов (запускать по расписанию, например раз в сутки — cron на хостинге):
 *  - файлы в папке загрузок, на которые нигде нет ссылок (ни в текущих данных, ни в хранимых версиях), удаляются;
 *  - файлы в папке загрузок, нужные только хранимым версиям (не сайту), уходят в карантин storage/quarantine
 *    (так админка поступает сама при замене и удалении; здесь — для файлов, оставшихся с прежних времён);
 *  - файлы в карантине, на которые больше не ссылается ни одна версия (версия ушла из последних 20), удаляются.
 * Админка заменённые файлы сразу не удаляет, пока их версия хранится (иначе «Вернуть эту версию» дала бы битые ссылки).
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
$kept = $app->keptUploadRefs();
$live = $app->liveUploadRefs();
$inventory = $uploads->inventory();
$orphans = $uploads->orphans($kept, $minAge);
// на сайте, но нужны только истории: в карантин
$hide = array_values(array_filter(
    array_keys($inventory),
    static fn (string $url): bool => isset($kept[$url]) && !isset($live[$url]) && time() - $inventory[$url] >= $minAge
));
sort($hide, SORT_STRING);
// в карантине и не нужны никому: удалить
$quarantined = $uploads->quarantineInventory();
$purge = array_values(array_filter(array_keys($quarantined), static fn (string $url): bool => !isset($kept[$url])));
sort($purge, SORT_STRING);
$total = count($inventory);
if (!$force && ($orphans !== [] || $hide !== [] || $purge !== [])) {
    if ($app->store()->versionCount() === 0) {
        fwrite(STDERR, "uploads-gc: в хранилище нет ни одной сохранённой версии — без истории не понять, что ещё нужно. Ничего не удалено (--force, если уверены).\n");
        exit(2);
    }
    if ((count($orphans) + count($hide)) * 2 > $total) {
        fwrite(STDERR, sprintf("uploads-gc: с сайта ушло бы %d из %d файлов (больше половины) — похоже, указана не та папка. Ничего не удалено (--force, если уверены).\n", count($orphans) + count($hide), $total));
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
foreach ($hide as $url) {
    if ($dry) {
        fwrite(STDOUT, "would quarantine $url\n");
        continue;
    }
    try {
        $uploads->quarantine($url);
        fwrite(STDOUT, "quarantined $url\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "cannot quarantine $url: " . $e->getMessage() . "\n");
    }
}
foreach ($purge as $url) {
    if ($dry) {
        fwrite(STDOUT, "would purge quarantined $url\n");
        continue;
    }
    try {
        $uploads->purgeQuarantined($url);
        fwrite(STDOUT, "purged quarantined $url\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "cannot purge quarantined $url: " . $e->getMessage() . "\n");
    }
}
fwrite(STDOUT, sprintf("uploads-gc: %s %d\n", $dry ? 'unreferenced files:' : 'removed:', $dry ? count($orphans) : count($orphans) - $failed));
fwrite(STDOUT, sprintf("uploads-gc: quarantine: %s %d, %s %d\n", $dry ? 'would move' : 'moved', count($hide), $dry ? 'would purge' : 'purged', count($purge)));
exit($failed > 0 ? 1 : 0);
