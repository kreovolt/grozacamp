<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\Support\Sanitizer;
use Groza\Admin\UploadException;
use Groza\App;
use Groza\Content\PageData;
use Groza\Data\ConflictException;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Pages\HomePage;
use Groza\Readiness\Readiness;
use Groza\Support\Csrf;

/**
 * Тексты сайта (storage/data/texts.json): одна страница на группу, все блоки группы — одной формой.
 * Описание блоков — config/texts.php; пункты 3–11 приказа № 201 — config/order201.php.
 *
 * Надёжность:
 *  - `_hash` файла на момент открытия: если файл с тех пор менялся — 409, ничего не пишется, ввод остаётся в форме;
 *  - незнакомый ключ в запросе — 400, не записывается ничего;
 *  - обязательный пункт приказа нельзя очистить случайно: без confirm_empty[ключ]=1 — 422 с вопросом, ввод остаётся;
 *  - текст с оформлением очищается Sanitizer'ом (граница безопасности), «пустым» считается и `<p> </p>`;
 *  - фото (type=image, например «Фото первого экрана»): описание делает сервер (Uploads), из запроса берутся только файл,
 *    подпись «Что на фото?» (обязательна) и выбор «оставить / убрать»; файл принимается после проверки остального,
 *    заменённый уходит по тем же правилам, что в редакторе списков (App::discardUploads: с учётом хранимых версий).
 */
final class TextsController
{
    public const MSG_CONFLICT = 'Пока вы редактировали, кто-то сохранил тексты. Ваши данные ниже — проверьте и сохраните ещё раз.';
    private const TOO_LONG = 'Слишком длинный текст';
    private const MAX_PLAIN = 2000;
    private const MAX_LINE = 500;
    private const MAX_LINES = 200;
    private const MAX_RICH = 400000;
    /** Длиннее — многострочное поле, короче — строка ввода. */
    private const TEXTAREA_FROM = 90;
    private const ALT_MAX = 200;

    /** Группы страниц админки: адрес → подпись. */
    public const GROUPS = [
        'home' => 'Главная',
        'info' => 'Родителям',
        'svedeniya' => 'Сведения',
        'vacancies' => 'Вакансии',
        'legal' => 'Документы о данных',
    ];

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function show(Request $r, array $params): Response
    {
        $group = $this->group($params);
        if ($group === null) {
            return $this->notFound();
        }
        $stored = $this->app->store()->read('texts');
        $values = [];
        foreach ($this->keys($group) as $key => $def) {
            $values[$key] = $def['type'] === 'image'
                ? self::imageValue($stored[$key] ?? null, null, false)
                : self::display($def['type'], $stored[$key] ?? null);
        }

        return $this->render($group, $values, [], [], 200, null, $r->query('saved') === '1');
    }

    /** @param array<string,string> $params */
    public function save(Request $r, array $params): Response
    {
        $group = $this->group($params);
        if ($group === null) {
            return $this->notFound();
        }
        $keys = $this->keys($group);
        $images = array_filter($keys, static fn (array $d): bool => $d['type'] === 'image');
        $posted = $r->post('text');
        if (!is_array($posted)) {
            return $this->badRequest('Форма отправлена неполностью. Откройте страницу заново.');
        }
        $unknown = array_values(array_diff(array_map('strval', array_keys($posted)), array_keys(array_diff_key($keys, $images))));
        if ($unknown !== []) {
            return $this->badRequest('В форме есть незнакомое поле: ' . mb_substr(implode(', ', $unknown), 0, 80) . '. Откройте страницу заново.');
        }
        $confirm = $r->post('confirm_empty');
        $confirm = is_array($confirm) ? $confirm : [];
        $postedHash = $r->post('_hash');
        $postedHash = is_string($postedHash) ? $postedHash : '';

        $store = $this->app->store();
        $current = $store->hash('texts'); // хеш — до данных: если файл изменят между чтением и записью, запись сама поймает расхождение
        $stored = $store->read('texts');

        $values = [];
        $clean = [];
        $errors = [];
        $needConfirm = [];
        $pending = [];
        $altPosted = $r->post('imgalt');
        $altPosted = is_array($altPosted) ? $altPosted : [];
        $actionPosted = $r->post('imgaction');
        $actionPosted = is_array($actionPosted) ? $actionPosted : [];
        foreach ($keys as $key => $def) {
            if ($def['type'] === 'image') {
                $photo = PageData::uploadedPhoto($stored[$key] ?? null);
                $file = $r->file(self::fileField($key));
                $uploaded = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if (!$uploaded && !array_key_exists($key, $altPosted) && !array_key_exists($key, $actionPosted)) {
                    $values[$key] = self::imageValue($stored[$key] ?? null, null, false); // поля фото не было в форме — не трогаем
                    continue;
                }
                $alt = self::cleanAlt($altPosted[$key] ?? '');
                $remove = ($actionPosted[$key] ?? 'keep') === 'remove';
                $keep = !$uploaded && !$remove ? $photo : null;
                $values[$key] = self::imageValue($stored[$key] ?? null, $alt, $remove);
                if (($uploaded || $keep !== null) && $alt === '') {
                    $errors[$key] = CollectionController::MSG_ALT;
                    continue;
                }
                if ($uploaded) {
                    $pending[$key] = $file;
                    $clean[$key] = ['alt' => $alt]; // описание файла — после загрузки
                } else {
                    $clean[$key] = $keep !== null ? array_replace($keep, ['alt' => $alt]) : null;
                }
                continue;
            }
            if (!array_key_exists($key, $posted)) {
                $values[$key] = self::display($def['type'], $stored[$key] ?? null);
                continue;
            }
            $raw = is_string($posted[$key]) ? $posted[$key] : '';
            $value = self::normalize($def['type'], $raw);
            if ($value === null) {
                $errors[$key] = self::TOO_LONG;
                $values[$key] = mb_substr($raw, 0, self::MAX_RICH);
                continue;
            }
            $clean[$key] = $value;
            $values[$key] = self::display($def['type'], $value);
            if (isset($def['required']) && $value === ($def['type'] === 'list' ? [] : '')
                && Readiness::filled($stored[$key] ?? null) && ($confirm[$key] ?? '') !== '1') {
                $needConfirm[$key] = true;
            }
        }

        if ($postedHash !== $current) {
            return $this->render($group, $values, [], [], 409, self::MSG_CONFLICT, false, null, $postedHash);
        }
        if ($errors || $needConfirm) {
            return $this->render($group, $values, $errors, $needConfirm, 422, null, false, $postedHash);
        }

        // Всё верно — только теперь принимаем фото (описание делает сервер)
        $created = [];
        foreach ($pending as $key => $file) {
            try {
                $d = $this->app->uploads()->storeImage($file);
            } catch (UploadException $e) {
                $this->app->discardUploads($created);

                return $this->render($group, $values, [$key => $e->getMessage()], [], 422, null, false, $postedHash);
            }
            $created[] = $d['src'];
            $clean[$key] = $d + ['alt' => (string) ($clean[$key]['alt'] ?? '')];
        }

        $next = array_replace($stored, $clean);
        if ($next !== $stored) { // ничего не изменилось — ни записи, ни строки в журнале
            try {
                $store->write('texts', $next, $this->app->actor(), $postedHash);
            } catch (ConflictException) {
                $this->app->discardUploads($created);

                return $this->render($group, $values, [], [], 409, self::MSG_CONFLICT, false, null, $postedHash);
            } catch (\Throwable $e) {
                $this->app->discardUploads($created);
                throw $e;
            }
            $this->app->audit()->record($this->app->actor(), 'texts.update', $group);
            // заменённое или убранное фото больше не нужно сайту (истории — может быть: тогда в карантин)
            $old = [];
            foreach (array_keys($images) as $key) {
                $was = PageData::uploadedPhoto($stored[$key] ?? null);
                $now = PageData::uploadedPhoto($next[$key] ?? null);
                if ($was !== null && ($now === null || $now['src'] !== $was['src'])) {
                    $old[] = $was['src'];
                }
            }
            $this->app->discardUploads($old);
        }

        return Response::redirect('/admin/texts/' . $group . '/?saved=1', 303);
    }

    /** Что пришло из формы → значение для хранения. null — слишком длинно. @return string|list<string>|null */
    private static function normalize(string $type, string $raw): string|array|null
    {
        $raw = str_replace(["\r\n", "\r"], "\n", mb_scrub($raw, 'UTF-8'));
        switch ($type) {
            case 'richtext':
                if (strlen($raw) > self::MAX_RICH) {
                    return null;
                }

                return Sanitizer::clean($raw);
            case 'list':
                $lines = [];
                foreach (explode("\n", $raw) as $line) {
                    $line = trim((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', ' ', $line));
                    if ($line === '') {
                        continue;
                    }
                    if (mb_strlen($line) > self::MAX_LINE) {
                        return null;
                    }
                    $lines[] = $line;
                }

                return count($lines) > self::MAX_LINES ? null : $lines;
            default:
                $s = trim((string) preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $raw));

                return mb_strlen($s) > self::MAX_PLAIN ? null : $s;
        }
    }

    /** Имя поля файла для фото-ключа (точки в именах верхнего уровня PHP заменил бы на «_» сам). */
    public static function fileField(string $key): string
    {
        return 'img_' . preg_replace('/[^A-Za-z0-9]/', '_', $key);
    }

    /**
     * Что показать в поле фото: сохранённое фото (или null — фото по умолчанию), подпись (введённая или сохранённая), «убрать».
     *
     * @return array{photo:?array<string,mixed>, alt:string, remove:bool}
     */
    private static function imageValue(mixed $stored, ?string $alt, bool $remove): array
    {
        $photo = PageData::uploadedPhoto($stored);

        return ['photo' => $photo, 'alt' => $alt ?? (string) ($photo['alt'] ?? ''), 'remove' => $remove];
    }

    /** Подпись к фото: одна строка, без управляющих знаков, не длиннее ALT_MAX. */
    private static function cleanAlt(mixed $v): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', mb_scrub((string) $v)));

        return trim(mb_substr($s, 0, self::ALT_MAX));
    }

    /** Значение в поле формы. */
    private static function display(string $type, mixed $value): string
    {
        if ($type === 'list') {
            return is_array($value) ? implode("\n", array_filter($value, 'is_string')) : '';
        }
        if (!is_string($value)) {
            return '';
        }

        // Старый текст без тегов показывается абзацами, чтобы редактор не склеил его в одну строку
        return $type === 'richtext' ? Sanitizer::clean($value) : $value;
    }

    /** @param array<string,string> $params */
    private function group(array $params): ?string
    {
        $g = $params['group'] ?? '';

        return isset(self::GROUPS[$g]) ? $g : null;
    }

    /** @return array<string,array<string,mixed>> ключи группы из реестра */
    private function keys(string $group): array
    {
        return array_filter(Readiness::registry(), static fn (array $d): bool => $d['group'] === $group);
    }

    /**
     * @param array<string,mixed> $values строка поля; у фото — {photo, alt, remove}
     * @param array<string,string> $errors
     * @param array<string,bool> $needConfirm
     */
    private function render(string $group, array $values, array $errors, array $needConfirm, int $status, ?string $banner, bool $saved, ?string $hash = null, ?string $basedOn = null): Response
    {
        $legal = [];
        foreach (Readiness::order() as $item) {
            if (str_starts_with((string) $item['source'], 'texts:')) {
                $legal[substr((string) $item['source'], 6)] = $item;
            }
        }
        $fields = [];
        $summary = [];
        foreach ($this->keys($group) as $key => $def) {
            $order = $legal[$key] ?? null;
            $code = $def['required'] ?? null;
            $fields[] = [
                'key' => $key,
                'label' => (string) $def['label'],
                'type' => (string) $def['type'],
                'value' => $values[$key] ?? '',
                'error' => $errors[$key] ?? null,
                'confirm' => isset($needConfirm[$key]),
                'required' => $code !== null,
                'code' => $code !== null ? (string) $code : ($order !== null ? str_replace(' ', '.', (string) $order['paragraph']) : null),
                'warning' => isset($needConfirm[$key]) && $code !== null
                    ? 'Это обязательный пункт для проверки (п. ' . $code . ' приказа № 201). Сохранить пустым?'
                    : null,
                'legalTitle' => $order !== null ? (string) $order['title'] : null,
                'legalFull' => $order !== null ? (string) $order['full'] : null,
                'multiline' => $def['type'] === 'list' || ($def['type'] === 'plain' && mb_strlen(is_string($values[$key] ?? null) ? $values[$key] : '') >= self::TEXTAREA_FROM),
                'fileField' => $def['type'] === 'image' ? self::fileField($key) : null,
            ];
            if (isset($errors[$key])) {
                $summary[] = ['id' => $key, 'label' => (string) $def['label'], 'message' => $errors[$key]];
            } elseif (isset($needConfirm[$key])) {
                $summary[] = ['id' => $key, 'label' => (string) $def['label'], 'message' => 'пусто — подтвердите, что так и нужно'];
            }
        }
        $now = $this->app->store()->hash('texts');

        return Response::html($this->app->view()->render('admin/texts', [
            'user' => $this->app->auth()->user(),
            'csrf' => Csrf::token(),
            'group' => $group,
            'groups' => self::GROUPS,
            'fields' => $fields,
            'summary' => $summary,
            'banner' => $banner,
            'saved' => $saved,
            'hash' => $hash ?? $now,
            'basedOn' => $basedOn ?? $hash ?? $now,
            'unsaved' => $status !== 200,
            'editor' => (bool) array_filter($fields, static fn (array $f): bool => $f['type'] === 'richtext'),
            'multipart' => (bool) array_filter($fields, static fn (array $f): bool => $f['type'] === 'image'),
            'defaultPhoto' => HomePage::HERO_PHOTO,
            'conflictText' => self::MSG_CONFLICT,
            'siteUrl' => ['home' => '/', 'info' => '/info/', 'svedeniya' => '/svedeniya/', 'vacancies' => '/vacancies/', 'legal' => '/policy/'][$group],
        ]), $status);
    }

    private function notFound(): Response
    {
        return Response::html($this->app->view()->render('admin/not-found', [
            'user' => $this->app->auth()->user(),
            'csrf' => Csrf::token(),
            'heading' => 'Такой страницы нет',
            'message' => 'Проверьте адрес или вернитесь к разделам.',
        ]), 404);
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
