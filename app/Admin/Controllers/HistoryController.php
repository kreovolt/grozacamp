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
        // Файлы возвращаемой версии, убранные в карантин, — сначала обратно на сайт; файлы нынешнего состояния, которые
        // после отката не нужны сайту, — в карантин (нынешнее состояние само станет версией, его можно вернуть)
        $raw = $store->versionRaw($name, $version);
        $before = $store->raw($name);
        if ($raw !== null) {
            $this->app->reviveUploads([$raw]);
        }
        try {
            $store->restore($name, $version, $this->app->actor());
        } catch (\InvalidArgumentException) {
            return $this->page('admin/not-found', ['heading' => 'Не получилось', 'message' => 'Эту версию вернуть нельзя.'], 400);
        }
        if ($before !== null) {
            $this->app->discardUploads(array_keys($this->app->uploads()->referencedIn([$before])));
        }
        $this->app->audit()->record($this->app->actor(), 'restore', $name . ':' . $version);

        return Response::redirect('/admin/history/?restored=' . $name, 303);
    }

    private function render(?string $notice, ?string $error, ?string $errorVersion, int $status = 200): Response
    {
        $files = [];
        $audit = $this->app->audit()->recent(500);
        $describer = new DashboardController($this->app);
        foreach (self::files() as $name => $label) {
            $versions = [];
            foreach ($this->app->store()->versions($name) as $v) {
                // метка версии — кто и когда ПЕРЕЗАПИСАЛ это состояние: версия хранит то, что было ДО его правки
                $what = $this->whatChanged($describer, $audit, $name, $v['actor'], $v['at']);
                $versions[] = [
                    'id' => $v['id'],
                    'at' => Dates::ruTime($v['at']),
                    'who' => $this->app->actorName($v['actor']),
                    'what' => $what,
                ];
            }
            $files[] = ['name' => $name, 'label' => $label, 'versions' => $versions];
        }

        return $this->page('admin/history', ['files' => $files, 'notice' => $notice, 'error' => $error, 'errorVersion' => $errorVersion], $status);
    }

    /**
     * Что сделал тот, кто перезаписал эту версию: запись журнала того же автора про тот же файл в пределах нескольких секунд.
     *
     * @param list<array{actor:string,action:string,target:string,at:string}> $audit
     */
    private function whatChanged(DashboardController $describer, array $audit, string $name, string $actor, string $at): string
    {
        $t = strtotime($at);
        if ($t === false) {
            return '';
        }
        $best = null;
        $bestDiff = 11;
        foreach ($audit as $row) {
            $action = (string) ($row['action'] ?? '');
            $target = (string) ($row['target'] ?? '');
            $mine = str_starts_with($action, $name . '.') || ($action === 'restore' && str_starts_with($target, $name . ':'));
            $rt = strtotime((string) ($row['at'] ?? ''));
            if (!$mine || $rt === false || ($row['actor'] ?? null) !== $actor || abs($rt - $t) >= $bestDiff) {
                continue;
            }
            $best = $row;
            $bestDiff = abs($rt - $t);
        }
        if ($best === null) {
            return '';
        }
        $d = $describer->describe((string) $best['action'], (string) $best['target']);

        return $d === null ? '' : $this->app->actorName($actor) . ' ' . $d[0] . ($d[1] !== '' ? ' «' . $d[1] . '»' : '');
    }

    /** @param array<string,mixed> $vars */
    private function page(string $template, array $vars, int $status = 200): Response
    {
        $vars += ['user' => $this->app->auth()->user(), 'csrf' => Csrf::token()];

        return Response::html($this->app->view()->render($template, $vars), $status);
    }
}
