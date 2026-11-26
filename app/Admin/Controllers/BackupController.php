<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;

/**
 * «Скачать резервную копию»: zip с data/*.json (без users и auth-meta — хешей паролей), uploads/img, uploads/docs
 * и quarantine/img, quarantine/docs (файлы, нужные только истории: без них откат из копии дал бы битые ссылки).
 * Архив собирается во временном файле в storage/tmp, отдаётся потоком и сразу удаляется. Состояния не меняет, поэтому GET.
 */
final class BackupController
{
    private bool $zip;

    public function __construct(private App $app, ?bool $zipAvailable = null)
    {
        $this->zip = $zipAvailable ?? class_exists(\ZipArchive::class);
    }

    /** @param array<string,string> $params */
    public function download(Request $r, array $params): Response
    {
        if (!$this->zip) {
            return $this->page('admin/not-found', [
                'heading' => 'Резервная копия недоступна',
                'message' => 'На сервере нет модуля zip — скачайте данные через хостинг (папки storage/data и uploads).',
            ], 503);
        }
        $tmpDir = $this->app->config()['storageDir'] . '/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            return $this->failed();
        }
        foreach (glob($tmpDir . '/backup-*.zip') ?: [] as $stale) { // остатки прерванных скачиваний
            if (is_file($stale) && time() - (int) filemtime($stale) > 3600) {
                @unlink($stale);
            }
        }
        // большая папка загрузок: сборка архива может занять дольше обычного предела времени запроса
        @set_time_limit(300);
        $tmp = $tmpDir . '/backup-' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return $this->failed();
        }
        try {
            $this->fill($zip);
            if (!$zip->close() || !is_file($tmp)) {
                throw new \RuntimeException('zip close failed');
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            error_log('Groza backup: ' . $e->getMessage());

            return $this->failed();
        }

        $this->app->audit()->record($this->app->actor(), 'backup.download', '-');
        $name = 'groza-backup-' . $this->app->clock()->now()->format('Y-m-d') . '.zip';

        return Response::file($tmp, 'application/zip', true)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function fill(\ZipArchive $zip): void
    {
        $dataDir = $this->app->config()['storageDir'] . '/data';
        // белый список: только известные хранилища сайта; чужой или будущий закрытый файл (users-old.json) в копию не попадёт
        foreach (array_keys(HistoryController::files()) as $name) {
            $path = $dataDir . '/' . $name . '.json';
            if (is_file($path) && !is_link($path)) {
                $zip->addFile($path, 'data/' . $name . '.json');
            }
        }
        foreach (['uploads' => $this->app->uploadsDir(), 'quarantine' => $this->app->quarantineDir()] as $prefix => $dir) {
            foreach (['img', 'docs'] as $kind) {
                foreach (glob($dir . '/' . $kind . '/*') ?: [] as $path) {
                    $file = basename($path);
                    // только обычные файлы с «нашими» именами: ни ссылок, ни скрытых, ни путей наружу
                    if (!is_file($path) || is_link($path) || preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]*$/', $file) !== 1 || str_contains($file, '..')) {
                        continue;
                    }
                    $entry = $prefix . '/' . $kind . '/' . $file;
                    $zip->addFile($path, $entry);
                    $zip->setCompressionName($entry, \ZipArchive::CM_STORE); // фото и PDF уже сжаты
                }
            }
        }
    }

    private function failed(): Response
    {
        return $this->page('admin/not-found', ['heading' => 'Не получилось', 'message' => 'Не удалось собрать архив. Попробуйте ещё раз или скачайте данные через хостинг.'], 500);
    }

    /** @param array<string,mixed> $vars */
    private function page(string $template, array $vars, int $status): Response
    {
        $vars += ['user' => $this->app->auth()->user(), 'csrf' => Csrf::token()];

        return Response::html($this->app->view()->render($template, $vars), $status);
    }
}
