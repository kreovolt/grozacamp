<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\App;
use Groza\Domain\Collections;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Readiness\Readiness;
use Groza\Support\Csrf;
use Groza\Support\Dates;

/**
 * Главная админки: «Готовность к проверке» (чек-лист по приказу № 201 со ссылками «Заполнить»), последние изменения, плитки разделов.
 */
final class DashboardController
{
    private const RECENT = 15;
    /** Сколько записей журнала просматриваем, чтобы набрать RECENT содержательных (входы и выходы в «изменения» не идут). */
    private const SCAN = 80;
    /** Что человек сделал с записью → глагол в прошедшем времени. */
    private const VERBS = ['create' => 'добавил(а)', 'update' => 'изменил(а)', 'delete' => 'удалил(а)'];
    /** Раздел → «кого/что» (винительный падеж). */
    private const NOUNS = [
        'shifts' => 'смену', 'venues' => 'базу', 'team' => 'сотрудника', 'documents' => 'документ',
        'vacancies' => 'вакансию', 'faq' => 'вопрос', 'reviews' => 'отзыв',
    ];

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function index(Request $r, array $params): Response
    {
        $sections = [];
        foreach (Collections::all() as $name => $def) {
            $sections[] = [
                'name' => $name,
                'label' => $def['label'],
                'count' => count($this->app->store()->read($name)['items'] ?? []),
            ];
        }

        $items = Readiness::evaluate(Readiness::dataFrom($this->app), $this->app->clock()->now());
        $required = array_values(array_filter($items, static fn (array $i): bool => $i['level'] === 'required'));
        $failed = array_values(array_filter($required, static fn (array $i): bool => !$i['ok']));
        $warnings = array_values(array_filter($items, static fn (array $i): bool => $i['level'] === 'warning' && !$i['ok']));

        return Response::html($this->app->view()->render('admin/dashboard', [
            'user' => $this->app->auth()->user(),
            'csrf' => Csrf::token(),
            'sections' => $sections,
            'textGroups' => TextsController::GROUPS,
            'ready' => $failed === [],
            'total' => count($required),
            'failedCount' => count($failed),
            'summary' => $failed === []
                ? 'Готово к проверке'
                : 'Не заполнено: ' . count($failed) . ' ' . self::plural(count($failed), ['обязательный пункт', 'обязательных пункта', 'обязательных пунктов']),
            'groups' => $this->group($failed),
            'warnings' => $warnings,
            'recent' => $this->recent(),
        ]));
    }

    /**
     * Недостающие пункты по разделам сведений, в порядке приказа.
     *
     * @param list<array<string,mixed>> $failed
     * @return list<array{label:string,items:list<array<string,mixed>>}>
     */
    private function group(array $failed): array
    {
        $by = [];
        foreach ($failed as $item) {
            $by[(string) $item['section']][] = $item;
        }
        $groups = [];
        foreach (Readiness::SECTIONS as $key => $label) {
            if (isset($by[$key])) {
                $groups[] = ['label' => $label, 'items' => $by[$key]];
                unset($by[$key]);
            }
        }
        foreach ($by as $key => $rest) { // раздел, которого нет в списке подписей, не теряем
            $groups[] = ['label' => (string) $key, 'items' => $rest];
        }

        return $groups;
    }

    /** @return list<array{who:string,when:string,what:string,title:string}> */
    private function recent(): array
    {
        $out = [];
        foreach ($this->app->audit()->recent(self::SCAN) as $row) {
            $described = $this->describe((string) ($row['action'] ?? ''), (string) ($row['target'] ?? ''));
            if ($described === null) {
                continue;
            }
            $out[] = [
                'who' => $this->app->actorName((string) ($row['actor'] ?? '')),
                'when' => Dates::ruTime((string) ($row['at'] ?? '')),
                'what' => $described[0],
                'title' => $described[1],
            ];
            if (count($out) >= self::RECENT) {
                break;
            }
        }

        return $out;
    }

    /**
     * Код действия из журнала → фраза по-русски и название записи (если она ещё есть). null — в «изменения» не берём.
     *
     * @return array{0:string,1:string}|null
     */
    public function describe(string $action, string $target): ?array
    {
        $groups = TextsController::GROUPS;
        $files = HistoryController::files();
        switch ($action) {
            case 'shifts.status':
                return ['изменил(а) статус смены', $this->title('shifts', $target)];
            case 'settings.update':
                return ['изменил(а) настройки и контакты', ''];
            case 'texts.update':
                return ['изменил(а) тексты сайта', $groups[$target] ?? ''];
            case 'restore':
                [$name] = explode(':', $target, 2);

                return ['вернул(а) прежнюю версию', $files[$name] ?? ''];
            case 'backup.download':
                return ['скачал(а) резервную копию', ''];
            case 'auth.password.change':
                return ['сменил(а) свой пароль', ''];
            case 'user.add':
                return ['создал(а) учётную запись', $target];
            case 'user.passwd':
                return ['сменил(а) пароль учётной записи', $target];
            case 'user.remove':
                return ['удалил(а) учётную запись', $target];
        }
        if (preg_match('/^([a-z0-9-]+)\.(create|update|delete|move|visibility)$/', $action, $m) === 1 && isset(self::NOUNS[$m[1]])) {
            [, $name, $kind] = $m;
            $label = (string) Collections::get($name)['label'];

            return match ($kind) {
                'move' => ['изменил(а) порядок в разделе «' . $label . '»', ''],
                'visibility' => ['изменил(а) видимость на сайте', $this->title($name, $target)],
                'delete' => ['удалил(а) ' . self::NOUNS[$name], ''], // записи уже нет — называть нечем
                default => [self::VERBS[$kind] . ' ' . self::NOUNS[$name], $this->title($name, $target)],
            };
        }
        if (str_starts_with($action, 'auth.')) {
            return null; // входы, выходы и неудачные попытки — не «изменения»
        }

        return [$action, '']; // неизвестное действие показываем как есть, а не прячем
    }

    /** Название записи, если она ещё существует; иначе пусто. */
    private function title(string $name, string $id): string
    {
        $fields = array_column(Collections::get($name)['fields'], 'name');
        foreach ($this->app->store()->read($name)['items'] ?? [] as $item) {
            if (($item['id'] ?? null) !== $id) {
                continue;
            }
            foreach (CollectionController::TITLE_FIELDS as $key) {
                if (in_array($key, $fields, true) && is_string($item[$key] ?? null) && trim($item[$key]) !== '') {
                    $t = trim($item[$key]);

                    return mb_strlen($t) > 70 ? mb_substr($t, 0, 70) . '…' : $t;
                }
            }
        }

        return '';
    }

    /** @param array{0:string,1:string,2:string} $forms один / два-четыре / пять и больше */
    private static function plural(int $n, array $forms): string
    {
        $m100 = $n % 100;
        $m10 = $n % 10;
        if ($m100 >= 11 && $m100 <= 14) {
            return $forms[2];
        }

        return match (true) {
            $m10 === 1 => $forms[0],
            $m10 >= 2 && $m10 <= 4 => $forms[1],
            default => $forms[2],
        };
    }
}
