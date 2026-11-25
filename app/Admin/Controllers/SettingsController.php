<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\App;
use Groza\Data\ConflictException;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Readiness\Readiness;
use Groza\Support\Csrf;
use Groza\Support\Phone;

/**
 * Настройки сайта: контакты, часы работы, ссылки на соцсети, получатель заявок, счётчик Метрики.
 * Хранятся в storage/data/settings.json ключ за ключом (чужие ключи при сохранении не теряются).
 *
 * Надёжность — как у редактора списков: `_hash` файла (409, если кто-то сохранил раньше, ввод остаётся в форме),
 * 422 с ошибками, пустой обязательный пункт (телефон, почтовый адрес — п. 7 приказа № 201) сохраняется только с подтверждением.
 */
final class SettingsController
{
    public const MSG_CONFLICT = 'Пока вы редактировали, кто-то сохранил настройки. Ваши данные ниже — проверьте и сохраните ещё раз.';
    public const MSG_HTTPS = 'Ссылка должна начинаться с https://';
    public const MSG_EMAIL = 'Введите один адрес электронной почты, например name@example.ru';
    public const MSG_PHONE = 'Введите российский номер, например +7 (342) 247-43-47';
    public const MSG_METRIKA = 'Номер счётчика — только цифры (до 12)';

    /**
     * Поля: подпись, подсказка, вид проверки, предел длины.
     *
     * @return array<string,array{label:string,hint:string,kind:'phone'|'email'|'url'|'digits'|'text',max:int,group:string}>
     */
    public static function fields(): array
    {
        return [
            'phone' => ['label' => 'Телефон', 'hint' => 'В любом виде: 8 342 247-43-47 или +7(342)2474347. На сайте покажется как +7 (342) 247-43-47.', 'kind' => 'phone', 'max' => 30, 'group' => 'Связь'],
            'whatsapp' => ['label' => 'WhatsApp', 'hint' => 'Номер, на который родители пишут в WhatsApp. Пусто — кнопка скроется.', 'kind' => 'phone', 'max' => 30, 'group' => 'Связь'],
            'email' => ['label' => 'Почта для посетителей сайта', 'hint' => 'Показывается на странице «Контакты». Пусто — адрес скроется.', 'kind' => 'email', 'max' => 120, 'group' => 'Связь'],
            'mailTo' => ['label' => 'Почта для заявок', 'hint' => 'Сюда приходят заявки с форм. Один адрес. Пусто — используется 2474347@bk.ru.', 'kind' => 'email', 'max' => 120, 'group' => 'Связь'],
            'hoursWeekdays' => ['label' => 'Часы работы в будни', 'hint' => 'Например: Пн–Пт 10:00–19:00', 'kind' => 'text', 'max' => 80, 'group' => 'Офис'],
            'hoursWeekend' => ['label' => 'Часы работы в выходные', 'hint' => 'Например: Сб–Вс 12:00–17:00', 'kind' => 'text', 'max' => 80, 'group' => 'Офис'],
            'officeAddress' => ['label' => 'Адрес офиса', 'hint' => 'Где родители могут нас найти.', 'kind' => 'text', 'max' => 250, 'group' => 'Офис'],
            'postalAddress' => ['label' => 'Почтовый адрес', 'hint' => 'С индексом, для писем.', 'kind' => 'text', 'max' => 250, 'group' => 'Офис'],
            'vk' => ['label' => 'ВКонтакте', 'hint' => 'Ссылка на группу, начинается с https://', 'kind' => 'url', 'max' => 300, 'group' => 'Соцсети'],
            'youtube' => ['label' => 'YouTube', 'hint' => 'Ссылка на канал, начинается с https://', 'kind' => 'url', 'max' => 300, 'group' => 'Соцсети'],
            'vkReviewsUrl' => ['label' => 'Обсуждение с отзывами во ВКонтакте', 'hint' => 'Ссылка «Все отзывы». Пусто — ведёт на группу.', 'kind' => 'url', 'max' => 300, 'group' => 'Соцсети'],
            'metrikaId' => ['label' => 'Номер счётчика Яндекс.Метрики', 'hint' => 'Только цифры. Пусто — счётчик выключен.', 'kind' => 'digits', 'max' => 12, 'group' => 'Статистика'],
        ];
    }

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function show(Request $r, array $params): Response
    {
        $stored = $this->app->settings();
        $values = [];
        foreach (self::fields() as $key => $f) {
            $values[$key] = self::display($f['kind'], $stored[$key] ?? '');
        }

        return $this->render($values, [], [], 200, null, $r->query('saved') === '1');
    }

    /** @param array<string,string> $params */
    public function save(Request $r, array $params): Response
    {
        $posted = $r->post('settings');
        if (!is_array($posted)) {
            return $this->badRequest('Форма отправлена неполностью. Откройте страницу заново.');
        }
        $unknown = array_values(array_diff(array_map('strval', array_keys($posted)), array_keys(self::fields())));
        if ($unknown !== []) {
            return $this->badRequest('В форме есть незнакомое поле: ' . mb_substr(implode(', ', $unknown), 0, 80) . '. Откройте страницу заново.');
        }
        $confirm = $r->post('confirm_empty');
        $confirm = is_array($confirm) ? $confirm : [];
        $postedHash = $r->post('_hash');
        $postedHash = is_string($postedHash) ? $postedHash : '';

        $store = $this->app->store();
        $current = $store->hash('settings');
        $stored = $store->read('settings');
        $merged = $this->app->settings();

        $errors = [];
        $needConfirm = [];
        $values = [];
        $clean = [];
        foreach (self::fields() as $key => $f) {
            if (!array_key_exists($key, $posted)) {
                $values[$key] = self::display($f['kind'], $merged[$key] ?? '');
                continue;
            }
            $raw = is_string($posted[$key]) ? $posted[$key] : '';
            $values[$key] = $raw;
            [$value, $error] = self::normalize($f['kind'], $raw, $f['max']);
            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }
            $clean[$key] = $value;
            if ($value === '' && self::requiredCode($key) !== null && trim((string) ($merged[$key] ?? '')) !== '' && ($confirm[$key] ?? '') !== '1') {
                $needConfirm[$key] = true;
            }
        }

        if ($postedHash !== $current) {
            return $this->render($values, [], [], 409, self::MSG_CONFLICT, false, null, $postedHash);
        }
        if ($errors || $needConfirm) {
            return $this->render($values, $errors, $needConfirm, 422, null, false, $postedHash);
        }

        $next = array_replace($stored, $clean);
        if ($next !== $stored) {
            try {
                $store->write('settings', $next, $this->app->actor(), $postedHash);
            } catch (ConflictException) {
                return $this->render($values, [], [], 409, self::MSG_CONFLICT, false, null, $postedHash);
            }
            $this->app->audit()->record($this->app->actor(), 'settings.update', 'settings');
        }

        return Response::redirect('/admin/settings/?saved=1', 303);
    }

    /** Код пункта приказа № 201 для обязательной настройки («7.а»), иначе null. */
    public static function requiredCode(string $key): ?string
    {
        foreach (Readiness::order() as $item) {
            if (($item['source'] ?? '') === 'settings:' . $key && ($item['level'] ?? '') === 'required') {
                return str_replace(' ', '.', (string) $item['paragraph']);
            }
        }

        return null;
    }

    /** @return array{0:string,1:?string} значение и текст ошибки */
    private static function normalize(string $kind, string $raw, int $max): array
    {
        $s = trim((string) preg_replace('/[\s\x00-\x1F\x7F\x{00A0}]+/u', ' ', mb_scrub($raw, 'UTF-8')));
        if ($s === '') {
            return ['', null];
        }
        if (mb_strlen($s) > $max) {
            return [$s, 'Слишком длинно: не больше ' . $max . ' знаков'];
        }

        return match ($kind) {
            'phone' => ($p = Phone::normalize($s)) !== null ? [$p, null] : [$s, self::MSG_PHONE],
            'email' => self::email($s),
            'url' => self::url($s),
            'digits' => preg_match('/^[0-9]{1,12}$/', $s) === 1 ? [$s, null] : [$s, self::MSG_METRIKA],
            default => [$s, null],
        };
    }

    /** @return array{0:string,1:?string} */
    private static function email(string $s): array
    {
        $ok = filter_var($s, FILTER_VALIDATE_EMAIL) !== false && preg_match('/^[^\s,;<>"\'()]+$/', $s) === 1;

        return $ok ? [$s, null] : [$s, self::MSG_EMAIL];
    }

    /** @return array{0:string,1:?string} только https:// с адресом сайта, без пробелов и кавычек */
    private static function url(string $s): array
    {
        $host = parse_url($s, PHP_URL_HOST);
        $ok = preg_match('#^https://[^\s"\'<>\\\\]+$#i', $s) === 1 && is_string($host) && $host !== '';

        return $ok ? [$s, null] : [$s, self::MSG_HTTPS];
    }

    private static function display(string $kind, mixed $value): string
    {
        $s = is_scalar($value) ? (string) $value : '';

        return $kind === 'phone' && Phone::normalize($s) !== null ? Phone::pretty((string) Phone::normalize($s)) : $s;
    }

    /**
     * @param array<string,string> $values
     * @param array<string,string> $errors
     * @param array<string,bool> $needConfirm
     */
    private function render(array $values, array $errors, array $needConfirm, int $status, ?string $banner, bool $saved, ?string $hash = null, ?string $basedOn = null): Response
    {
        $fields = [];
        $summary = [];
        foreach (self::fields() as $key => $f) {
            $code = self::requiredCode($key);
            $fields[] = $f + [
                'key' => $key,
                'value' => $values[$key] ?? '',
                'error' => $errors[$key] ?? null,
                'confirm' => isset($needConfirm[$key]),
                'warning' => isset($needConfirm[$key]) && $code !== null
                    ? 'Это обязательный пункт для проверки (п. ' . $code . ' приказа № 201). Сохранить пустым?'
                    : null,
                'code' => $code,
            ];
            if (isset($errors[$key])) {
                $summary[] = ['id' => $key, 'label' => $f['label'], 'message' => $errors[$key]];
            } elseif (isset($needConfirm[$key])) {
                $summary[] = ['id' => $key, 'label' => $f['label'], 'message' => 'пусто — подтвердите, что так и нужно'];
            }
        }
        $now = $this->app->store()->hash('settings');

        return Response::html($this->app->view()->render('admin/settings', [
            'user' => $this->app->auth()->user(),
            'csrf' => Csrf::token(),
            'fields' => $fields,
            'summary' => $summary,
            'banner' => $banner,
            'saved' => $saved,
            'hash' => $hash ?? $now,
            'basedOn' => $basedOn ?? $hash ?? $now,
            'unsaved' => $status !== 200,
            'conflictText' => self::MSG_CONFLICT,
        ]), $status);
    }

    private function badRequest(string $message): Response
    {
        return Response::html($this->app->view()->render('admin/not-found', [
            'user' => $this->app->auth()->user(),
            'csrf' => Csrf::token(),
            'heading' => 'Не получилось',
            'message' => $message,
        ]), 400);
    }
}
