<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\App;
use Groza\Domain\Collections;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;
use Groza\Support\Dates;

/**
 * «История изменений»: по каждому файлу данных — сохранённые версии (последние 20) и откат в один клик.
 *
 * Откат — только POST с токеном и с отметкой «Да, вернуть» (проверяется на сервере). Имя файла берётся из белого списка,
 * версия — только из тех, что есть в истории файла. users и auth-meta (хеши паролей) сюда не попадают никогда.
 */
final class HistoryController
{
    private const EXTRA = ['texts' => 'Тексты сайта', 'settings' => 'Настройки и контакты'];
    public const MSG_CONFIRM = 'Отметьте «Да, вернуть», чтобы вернуть эту версию.';

    public function __construct(private App $app)
    {
    }

    /** @return array<string,string> файл данных → название раздела */
    public static function files(): array
    {
        $files = [];
        foreach (Collections::all() as $name => $def) {
            $files[$name] = (string) $def['label'];
        }

        return $files + self::EXTRA;
    }

    /** @param array<string,string> $params */
    public function index(Request $r, array $params): Response
    {
        $done = $r->query('restored');
        $notice = is_string($done) && isset(self::files()[$done]) ? 'Версия возвращена: «' . self::files()[$done] . '». Прежнее состояние тоже сохранилось в истории — при необходимости его можно вернуть.' : null;

        return $this->render($notice, null, null);
    }

    /** @param array<string,string> $params */
    public function restore(Request $r, array $params): Response
    {
        $name = $r->post('name');
        $version = $r->post('version');
        $store = $this->app->store();
        $ids = is_string($name) && isset(self::files()[$name]) ? array_column($store->versions($name), 'id') : [];
        if (!is_string($name) || !is_string($version) || !in_array($version, $ids, true)) {
            return $this->page('admin/not-found', ['heading' => 'Не получилось', 'message' => 'Такой версии нет в истории. Откройте историю и выберите версию из списка.'], 400);
        }
        if ($r->post('confirm') !== '1') {
            return $this->render(null, self::MSG_CONFIRM, $version, 422);
        }
        try {
            $store->restore($name, $version, $this->app->actor());
        } catch (\InvalidArgumentException) {
            return $this->page('admin/not-found', ['heading' => 'Не получилось', 'message' => 'Эту версию вернуть нельзя.'], 400);
        }
        $this->app->audit()->record($this->app->actor(), 'restore', $name . ':' . $version);

        return Response::redirect('/admin/history/?restored=' . $name, 303);
    }

    private function render(?string $notice, ?string $error, ?string $errorVersion, int $status = 200): Response
    {
        $files = [];
        foreach (self::files() as $name => $label) {
            $versions = [];
            foreach ($this->app->store()->versions($name) as $v) {
                $versions[] = [
                    'id' => $v['id'],
                    'at' => Dates::ruTime($v['at']),
                    'who' => $this->app->actorName($v['actor']),
                ];
            }
            $files[] = ['name' => $name, 'label' => $label, 'versions' => $versions];
        }

        return $this->page('admin/history', ['files' => $files, 'notice' => $notice, 'error' => $error, 'errorVersion' => $errorVersion], $status);
    }

    /** @param array<string,mixed> $vars */
    private function page(string $template, array $vars, int $status = 200): Response
    {
        $vars += ['user' => $this->app->auth()->user(), 'csrf' => Csrf::token()];

        return Response::html($this->app->view()->render($template, $vars), $status);
    }
}
