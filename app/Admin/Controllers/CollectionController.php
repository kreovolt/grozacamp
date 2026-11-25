<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\Admin\FormRenderer;
use Groza\Admin\UploadException;
use Groza\App;
use Groza\Data\ConflictException;
use Groza\Domain\Collections;
use Groza\Domain\Shifts;
use Groza\Domain\ValidationException;
use Groza\Domain\Validator;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Pages\SvedeniyaPage;
use Groza\Support\Csrf;

/**
 * Универсальный редактор списков: форма строится из описания коллекции (Collections), поэтому для разных списков
 * отличаются только данные. Особые места — маршрут статуса смены и столбцы списка смен.
 *
 * Надёжность:
 *  - форма правки несёт `_hash` файла на момент открытия; если файл с тех пор менялся — 409, ничего не пишется,
 *    человек видит свои значения и свежий `_hash` (второе нажатие «Сохранить» — осознанное);
 *  - быстрые действия (статус, порядок, удаление) читают и пишут с проверкой хеша и повторяют попытку при гонке;
 *  - описание фото/файла никогда не берётся из запроса: его делает Uploads, и только после того, как остальные поля прошли проверку.
 */
final class CollectionController
{
    public const MSG_DELETED = 'Эту запись удалили, пока вы её редактировали. Ваши данные ниже — если они нужны, нажмите «Сохранить»: запись будет создана заново.';
    public const MSG_CONFLICT = 'Пока вы редактировали, кто-то сохранил изменения. Ваши данные ниже — проверьте и сохраните ещё раз.';
    private const ID = '/^[a-f0-9]{12}$/';
    private const RETRIES = 3;
    /** Поля, из которых берётся название записи в списке (первое заполненное). */
    public const TITLE_FIELDS = ['title', 'name', 'question', 'text', 'author'];
    private const FLASH = ['status' => 'Статус изменён', 'delete' => 'Запись удалена', 'visibility' => 'Видимость на сайте изменена'];
    public const MSG_ALT = 'Опишите, что на фото, — это нужно незрячим посетителям';
    private const ALT_MAX = 200;
    /** Не больше стольких новых фото за одно сохранение галереи. */
    private const NEW_PHOTOS_MAX = 20;
    private const PLACEHOLDER = 'pending-upload';

    public function __construct(private App $app)
    {
    }

    // ---------- страницы ----------

    /** @param array<string,string> $params */
    public function list(Request $r, array $params): Response
    {
        $name = $this->name($params);
        if ($name === null) {
            return $this->notFound();
        }
        $def = Collections::get($name);
        $isShifts = $name === 'shifts';
        $orderable = ($def['orderable'] ?? true) !== false;
        $rows = [];
        $items = $this->ordered($name, $def);
        foreach ($items as $i => $item) {
            $rows[] = [
                'id' => (string) ($item['id'] ?? ''),
                'title' => $this->itemTitle($def, $item),
                'facts' => $this->facts($name, $def, $item),
                'hidden' => !empty($item['hidden']) || (array_key_exists('visible', $item) && empty($item['visible'])),
                'canHide' => $this->hideField($def) !== null,
                'status' => $isShifts ? (string) ($item['status'] ?? '') : null,
                'first' => $i === 0,
                'last' => $i === count($items) - 1,
            ];
        }
        $flash = self::FLASH[(string) $r->query('ok')] ?? null;

        return $this->page('admin/collection-list', [
            'collection' => $name,
            'def' => $def,
            'rows' => $rows,
            'orderable' => $orderable,
            'statusOptions' => $isShifts ? Shifts::STATUS_LABELS : null,
            'counter' => $this->counter($def, $items),
            'flash' => $flash,
        ]);
    }

    /** @param array<string,string> $params */
    public function showNew(Request $r, array $params): Response
    {
        $name = $this->name($params);
        if ($name === null) {
            return $this->notFound();
        }

        // Ссылки «Заполнить» с панели готовности передают нужный раздел (?section=…): подставляем его, если такой вариант есть
        $preset = [];
        foreach (Collections::get($name)['fields'] as $f) {
            $given = $r->query((string) $f['name']);
            if ($f['type'] === 'enum' && $given !== null && isset($f['options'][$given])) {
                $preset[(string) $f['name']] = $given;
            }
        }

        return $this->editPage($name, 'new', $preset, [], 200);
    }

    /** @param array<string,string> $params */
    public function show(Request $r, array $params): Response
    {
        $name = $this->name($params);
        $id = $this->id($params);
        if ($name === null || $id === null || $id === 'new') {
            return $this->notFound();
        }
        $item = $this->find($name, $id);
        if ($item === null) {
            return $this->notFound();
        }

        return $this->editPage($name, $id, $this->displayValues(Collections::get($name), [], $item), [], 200, null, $r->query('saved') === '1');
    }

    /** @param array<string,string> $params */
    public function save(Request $r, array $params): Response
    {
        $name = $this->name($params);
        $id = $this->id($params);
        if ($name === null || $id === null) {
            return $this->notFound();
        }
        $def = Collections::get($name);
        $isNew = $id === 'new';
        $store = $this->app->store();
        $postedHash = $r->post('_hash');
        $postedHash = is_string($postedHash) ? $postedHash : '';

        // Хеш читаем до данных: если файл изменят между чтением и записью, запись сама поймает расхождение
        $current = $store->hash($name);
        $items = array_values($store->read($name)['items'] ?? []);
        $existing = [];
        $index = null;
        if (!$isNew) {
            foreach ($items as $i => $item) {
                if (($item['id'] ?? null) === $id) {
                    $index = $i;
                    $existing = $item;
                }
            }
            if ($index === null) {
                // Запись удалили, пока её правили: введённое не теряем — форма предлагает создать её заново
                return $this->editPage($name, 'new', $this->displayValues($def, $r->allPost(), []), [], 409, self::MSG_DELETED);
            }
        }
        $display = $this->displayValues($def, $r->allPost(), $existing);

        if ($postedHash !== $current) {
            return $this->conflict($name, $id, $display, $postedHash);
        }

        // 1. Проверка всех полей. Файлы пока не загружаем: вместо них подставляем «будет загружено».
        [$input, $pending, $alts, $altErrors] = $this->input($def, $r, $existing);
        $errors = [];
        $data = [];
        try {
            $data = Validator::validate($def, $input, $this->refSlugs($def));
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }
        $errors += $altErrors;
        if (!$errors) {
            $errors = $this->uniqueSlug($def, $items, $data, $id);
        }
        if ($errors) {
            return $this->editPage($name, $id, $display, $errors, 422, null, false, $postedHash);
        }

        // 2. Всё верно — только теперь принимаем файлы (описание делает сервер)
        $created = [];
        $uploadErrors = $this->storeUploads($def, $pending, $alts, $data, $created);
        if ($uploadErrors) {
            return $this->editPage($name, $id, $display, $uploadErrors, 422, null, false, $postedHash);
        }

        // 3. Запись с проверкой хеша
        $now = $this->app->clock()->now()->format('c');
        $actor = $this->app->actor();
        $record = $data + ['id' => $isNew ? $this->newId($items) : $id, 'updatedAt' => $now, 'updatedBy' => $actor];
        if ($isNew) {
            $record = $this->withOrderAtEnd($def, $record, $items);
            $items[] = $record;
        } else {
            $items[$index] = $record;
        }
        try {
            $store->write($name, ['items' => $items], $actor, $postedHash);
        } catch (ConflictException) {
            $this->discard($created);

            return $this->conflict($name, $id, $display, $postedHash);
        } catch (\Throwable $e) {
            $this->discard($created);
            throw $e;
        }

        // 4. Сохранено: старые файлы, которые заменены или убраны, больше не нужны
        $this->discard(array_diff($this->fileRefs($def, $existing), $this->fileRefs($def, $record)));
        $this->app->audit()->record($actor, $name . ($isNew ? '.create' : '.update'), $record['id']);

        return Response::redirect('/admin/' . $name . '/' . $record['id'] . '/?saved=1' . ($isNew ? '&created=1' : ''), 303);
    }

    /** Страница «Удалить?». Сам запрос на удаление — только POST. @param array<string,string> $params */
    public function confirmDelete(Request $r, array $params): Response
    {
        $name = $this->name($params);
        $id = $this->id($params);
        $item = $name !== null && $id !== null && $id !== 'new' ? $this->find($name, $id) : null;
        if ($name === null || $item === null) {
            return $this->notFound();
        }

        return $this->deletePage($name, $item);
    }

    /** @param array<string,string> $params */
    public function delete(Request $r, array $params): Response
    {
        $name = $this->name($params);
        $id = $this->id($params);
        if ($name === null || $id === null || $id === 'new') {
            return $this->notFound();
        }
        $item = $this->find($name, $id);
        if ($item === null) {
            return Response::redirect('/admin/' . $name . '/?ok=delete', 303); // уже удалена (например, вторым нажатием)
        }
        if ($r->post('confirm') !== '1') {
            return $this->deletePage($name, $item);
        }
        $removed = null;
        $this->mutate($name, function (array $items) use ($id, &$removed): array {
            foreach ($items as $i) {
                if (($i['id'] ?? null) === $id) {
                    $removed = $i;
                }
            }

            return array_values(array_filter($items, static fn (array $i): bool => ($i['id'] ?? null) !== $id));
        });
        if ($removed !== null) {
            $this->discard($this->fileRefs(Collections::get($name), $removed));
            $this->app->audit()->record($this->app->actor(), $name . '.delete', $id);
        }

        return Response::redirect('/admin/' . $name . '/?ok=delete', 303);
    }

    /** @param array<string,string> $params */
    public function move(Request $r, array $params): Response
    {
        $name = $this->name($params);
        $id = $this->id($params);
        if ($name === null || $id === null || $id === 'new' || $this->find($name, $id) === null) {
            return $this->notFound();
        }
        $dir = $r->post('dir');
        if (!in_array($dir, ['up', 'down'], true)) {
            return $this->badRequest('Не понятно, куда переместить запись.');
        }
        $def = Collections::get($name);
        if (($def['orderable'] ?? true) === false) {
            return $this->badRequest('У этого раздела порядок задаётся датами, переставлять не нужно.');
        }
        $hasOrder = in_array('order', array_column($def['fields'], 'name'), true);
        $moved = $this->mutate($name, function (array $items) use ($id, $dir, $hasOrder): ?array {
            if ($hasOrder) { // порядок задаёт поле order (шаг 10), читаем по нему
                usort($items, static fn (array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));
            }
            $pos = null;
            foreach ($items as $i => $item) {
                if (($item['id'] ?? null) === $id) {
                    $pos = $i;
                }
            }
            $to = $pos === null ? -1 : ($dir === 'up' ? $pos - 1 : $pos + 1);
            if ($to < 0 || $to >= count($items)) {
                return null;
            }
            [$items[$pos], $items[$to]] = [$items[$to], $items[$pos]];
            if ($hasOrder) {
                foreach (array_keys($items) as $i) {
                    $items[$i]['order'] = ($i + 1) * 10;
                }
            }

            return $items;
        });
        if ($moved) {
            $this->app->audit()->record($this->app->actor(), $name . '.move', $id);
        }

        return Response::redirect('/admin/' . $name . '/#row-' . $id, 303);
    }

    /** Быстрая смена статуса смены (кнопки в списке). @param array<string,string> $params */
    public function status(Request $r, array $params): Response
    {
        $id = $this->id($params);
        if ($id === null || $id === 'new' || $this->find('shifts', $id) === null) {
            return $this->notFound();
        }
        $status = $r->post('status');
        if (!is_string($status) || !array_key_exists($status, Shifts::STATUS_LABELS)) {
            return $this->badRequest('Такого статуса нет.');
        }
        $actor = $this->app->actor();
        $now = $this->app->clock()->now()->format('c');
        $changed = $this->mutate('shifts', function (array $items) use ($id, $status, $actor, $now): ?array {
            $hit = false;
            foreach ($items as $i => $item) {
                if (($item['id'] ?? null) === $id && ($item['status'] ?? null) !== $status) {
                    $items[$i]['status'] = $status;
                    $items[$i]['updatedAt'] = $now;
                    $items[$i]['updatedBy'] = $actor;
                    $hit = true;
                }
            }

            return $hit ? $items : null;
        });
        if ($changed) {
            $this->app->audit()->record($actor, 'shifts.status', $id);
        }

        return Response::redirect('/admin/shifts/?ok=status#row-' . $id, 303);
    }

    /**
     * Быстро скрыть запись с сайта или вернуть (кнопка в списке): меняет поле «hidden» (у отзывов — «visible»).
     *
     * @param array<string,string> $params
     */
    public function visibility(Request $r, array $params): Response
    {
        $name = $this->name($params);
        $id = $this->id($params);
        $field = $name !== null ? $this->hideField(Collections::get($name)) : null;
        if ($name === null || $id === null || $id === 'new' || $field === null || $this->find($name, $id) === null) {
            return $this->notFound();
        }
        $state = $r->post('state');
        if (!in_array($state, ['hide', 'show'], true)) {
            return $this->badRequest('Не понятно, скрыть запись или показать.');
        }
        // «hidden»: скрыть = true; «visible»: скрыть = false
        $value = $field === 'hidden' ? $state === 'hide' : $state === 'show';
        $actor = $this->app->actor();
        $now = $this->app->clock()->now()->format('c');
        $changed = $this->mutate($name, function (array $items) use ($id, $field, $value, $actor, $now): ?array {
            $hit = false;
            foreach ($items as $i => $item) {
                if (($item['id'] ?? null) === $id && (($item[$field] ?? null) !== $value)) {
                    $items[$i][$field] = $value;
                    $items[$i]['updatedAt'] = $now;
                    $items[$i]['updatedBy'] = $actor;
                    $hit = true;
                }
            }

            return $hit ? $items : null;
        });
        if ($changed) {
            $this->app->audit()->record($actor, $name . '.visibility', $id);
        }

        return Response::redirect('/admin/' . $name . '/?ok=visibility#row-' . $id, 303);
    }

    // ---------- сборка страниц ----------

    /**
     * @param array<string,mixed> $values
     * @param array<string,string> $errors
     */
    private function editPage(string $name, string $id, array $values, array $errors, int $status, ?string $banner = null, bool $saved = false, ?string $hash = null, ?string $basedOn = null): Response
    {
        $def = Collections::get($name);
        $isNew = $id === 'new';
        $item = $isNew ? [] : ($this->find($name, $id) ?? []);
        $renderer = new FormRenderer($this->app->view(), $this->refOptions($def));
        $summary = [];
        foreach ($def['fields'] as $f) {
            if (isset($errors[$f['name']])) {
                $summary[] = ['id' => 'f-' . $f['name'], 'label' => (string) $f['label'], 'message' => $errors[$f['name']]];
            }
            if (isset($errors[$f['name'] . '__alt'])) {
                $summary[] = ['id' => 'f-' . $f['name'] . ($f['type'] === 'gallery' ? '' : '__alt'), 'label' => (string) $f['label'] . ': что на фото', 'message' => $errors[$f['name'] . '__alt']];
            }
        }
        $pattern = $def['publicUrl'];

        return $this->page('admin/collection-edit', [
            'collection' => $name,
            'def' => $def,
            'id' => $id,
            'isNew' => $isNew,
            'heading' => $isNew ? 'Новая запись' : $this->itemTitle($def, $item),
            'form' => $renderer->render($def, $values, $errors),
            // 422: тот же _hash, что прислан (чужая правка между отправками должна дать 409); 409 и открытие — свежий
            'hash' => $hash ?? $this->app->store()->hash($name),
            'unsaved' => $status !== 200,
            // состояние файла, на котором основан ввод: при 409 это присланный (устаревший) хеш, не свежий
            'basedOn' => $basedOn ?? $hash ?? $this->app->store()->hash($name),
            'conflictText' => self::MSG_CONFLICT,
            'summary' => $summary,
            'banner' => $banner,
            'saved' => $saved,
            'hasRichtext' => (bool) array_filter($def['fields'], static fn (array $f): bool => $f['type'] === 'richtext'),
            'hasFiles' => (bool) array_filter($def['fields'], static fn (array $f): bool => in_array($f['type'], ['image', 'file'], true)),
            'siteUrl' => $pattern !== null ? $this->siteUrl($pattern, $item) : null,
            'sitePattern' => $pattern !== null ? str_replace('{slug}', 'адрес-страницы', $pattern) : null,
        ], $status);
    }

    /** @param array<string,mixed> $display */
    private function conflict(string $name, string $id, array $display, string $postedHash): Response
    {
        return $this->editPage($name, $id, $display, [], 409, self::MSG_CONFLICT, false, null, $postedHash);
    }

    /** @param array<string,mixed> $item */
    private function deletePage(string $name, array $item): Response
    {
        $def = Collections::get($name);

        return $this->page('admin/collection-delete', [
            'collection' => $name,
            'def' => $def,
            'id' => (string) $item['id'],
            'heading' => $this->itemTitle($def, $item),
        ]);
    }

    private function notFound(): Response
    {
        return $this->page('admin/not-found', ['heading' => 'Такой страницы нет', 'message' => 'Проверьте адрес или вернитесь к разделам.'], 404);
    }

    private function badRequest(string $message): Response
    {
        return $this->page('admin/not-found', ['heading' => 'Не получилось', 'message' => $message], 422);
    }

    /** @param array<string,mixed> $vars */
    private function page(string $template, array $vars, int $status = 200): Response
    {
        $vars += ['user' => $this->app->auth()->user(), 'csrf' => Csrf::token()];

        return Response::html($this->app->view()->render($template, $vars), $status);
    }

    // ---------- вход формы ----------

    /**
     * Что пришло из формы → вход для Validator. Фото и файлы: берётся только выбор «оставить / убрать», факт загрузки
     * и текст подписи; любое «готовое описание» в запросе игнорируется.
     *
     * Галерея: из запроса берутся только «метки» уже имеющихся фото (порядок, «убрать», подпись) и новые файлы;
     * сами описания фото берутся из сохранённой записи.
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $existing
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>,3:array<string,string>} [вход, отложенные загрузки, подписи новых фото, ошибки подписей]
     */
    private function input(array $def, Request $r, array $existing): array
    {
        $post = $r->allPost();
        $input = [];
        $pending = [];
        $alts = [];
        $altErrors = [];
        foreach ($def['fields'] as $f) {
            $n = (string) $f['name'];
            switch ($f['type']) {
                case 'image':
                case 'file':
                    $file = $r->file($n);
                    $action = $post[$n . '__action'] ?? 'keep';
                    $uploaded = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                    if ($uploaded) {
                        $pending[$n] = $file;
                        $input[$n] = $f['type'] === 'image' ? self::PLACEHOLDER : ['url' => '/uploads/docs/pending', 'bytes' => 0, 'originalName' => 'pending'];
                    } else {
                        $input[$n] = $action === 'remove' ? null : ($existing[$n] ?? null);
                    }
                    if ($f['type'] === 'image') {
                        $alt = $this->cleanAlt($post[$n . '__alt'] ?? '');
                        $alts[$n] = $alt;
                        if (is_array($input[$n])) {
                            $input[$n]['alt'] = $alt; // подпись уже загруженного фото меняется из поля формы
                        }
                        // Подпись обязательна у своего (загруженного) фото; фото из библиотеки сайта подписано в библиотеке
                        if (($uploaded || is_array($input[$n])) && $alt === '') {
                            $altErrors[$n . '__alt'] = self::MSG_ALT;
                        }
                    }
                    break;
                case 'gallery':
                    [$input[$n], $pending[$n], $alts[$n], $missing] = $this->galleryInput((array) ($existing[$n] ?? []), $post, $r, $n);
                    if ($pending[$n] === []) {
                        unset($pending[$n]);
                    }
                    if ($missing) {
                        $altErrors[$n . '__alt'] = self::MSG_ALT;
                    }
                    break;
                case 'list':
                    $raw = $post[$n] ?? '';
                    $input[$n] = is_string($raw) ? (preg_split('/\R/u', $raw) ?: []) : [];
                    break;
                default:
                    $input[$n] = $post[$n] ?? null;
            }
        }

        return [$input, $pending, $alts, $altErrors];
    }

    /**
     * Галерея: что оставить (по меткам), в каком порядке и с какими подписями, плюс новые файлы.
     *
     * @param array<int,mixed> $existing
     * @param array<string,mixed> $post
     * @return array{0:list<mixed>,1:list<array<string,mixed>>,2:list<string>,3:bool} [список для проверки, новые файлы, их подписи, не хватает подписи]
     */
    private function galleryInput(array $existing, array $post, Request $r, string $n): array
    {
        $list = [];
        $missing = false;
        foreach ($this->galleryRows($existing, $post, $n, true) as $row) {
            if ($row['del']) {
                continue;
            }
            $value = $row['value'];
            if (is_array($value)) {
                $value['alt'] = $row['alt'];
                $missing = $missing || $row['alt'] === '';
            }
            $list[] = $value;
        }
        $files = [];
        $alts = [];
        $slots = [];
        foreach ($r->files() as $key => $file) {
            if (preg_match('/^' . preg_quote($n, '/') . '__new_(\d{1,3})$/', (string) $key, $m) === 1
                && is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $slots[(int) $m[1]] = $file;
            }
        }
        ksort($slots);
        foreach (array_slice($slots, 0, self::NEW_PHOTOS_MAX, true) as $num => $file) {
            $alt = $this->cleanAlt($post[$n . '__newalt_' . $num] ?? '');
            $missing = $missing || $alt === '';
            $files[] = $file;
            $alts[] = $alt;
            $list[] = self::PLACEHOLDER;
        }

        return [$list, $files, $alts, $missing];
    }

    /**
     * Строки галереи: фото из записи в порядке запроса (не названные — в конец), с подписью и отметкой «убрать».
     * Фото узнаётся по метке (ключ библиотеки или имя файла), а не по номеру: если запись тем временем изменили, чужое фото не заденется.
     *
     * @param array<int,mixed> $existing
     * @param array<string,mixed> $post
     * @return list<array{token:string,value:mixed,alt:string,del:bool}>
     */
    private function galleryRows(array $existing, array $post, string $n, bool $submitted): array
    {
        $byToken = [];
        foreach (array_values($existing) as $v) {
            $byToken[$this->photoToken($v)] ??= $v;
        }
        $order = [];
        $posted = $submitted ? ($post[$n . '__old'] ?? null) : null;
        foreach (is_array($posted) ? $posted : [] as $t) {
            if (is_string($t) && isset($byToken[$t]) && !in_array($t, $order, true)) {
                $order[] = $t;
            }
        }
        foreach (array_keys($byToken) as $t) {
            if (!in_array((string) $t, $order, true)) {
                $order[] = (string) $t;
            }
        }
        $rows = [];
        foreach ($order as $t) {
            $v = $byToken[$t];
            $rows[] = [
                'token' => $t,
                'value' => $v,
                'alt' => $submitted
                    ? $this->cleanAlt($post[$n . '__alt_' . $t] ?? '')
                    : (is_array($v) && is_string($v['alt'] ?? null) ? $v['alt'] : ''),
                'del' => $submitted && !empty($post[$n . '__del_' . $t]) && $post[$n . '__del_' . $t] !== '0',
            ];
        }

        return $rows;
    }

    /** Метка фото в форме: ключ библиотеки сайта или имя файла загрузки; безопасна для имени поля. */
    private function photoToken(mixed $photo): string
    {
        if (is_string($photo) && preg_match('/^[a-z0-9-]+$/', $photo) === 1) {
            return $photo;
        }
        if (is_array($photo) && is_string($photo['src'] ?? null)) {
            $base = basename($photo['src']);
            if (preg_match('/^[0-9a-f]{16}$/', $base) === 1) {
                return $base;
            }
        }

        return 'x' . substr(md5((string) json_encode($photo)), 0, 12);
    }

    /** Подпись к фото: одна строка, без управляющих знаков, не длиннее ALT_MAX. */
    private function cleanAlt(mixed $v): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', mb_scrub((string) $v)));

        return trim(mb_substr($s, 0, self::ALT_MAX));
    }

    /**
     * Загружает отложенные файлы; описания (с подписями) пишет в $data. При ошибке убирает уже загруженное.
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $pending
     * @param array<string,mixed> $alts
     * @param array<string,mixed> $data
     * @param list<string> $created
     * @return array<string,string> ошибки по полям
     */
    private function storeUploads(array $def, array $pending, array $alts, array &$data, array &$created): array
    {
        $uploads = $this->app->uploads();
        foreach ($def['fields'] as $f) {
            $n = (string) $f['name'];
            if (!isset($pending[$n])) {
                continue;
            }
            try {
                if ($f['type'] === 'image') {
                    $data[$n] = $uploads->storeImage($pending[$n]);
                    $created[] = $data[$n]['src'];
                    $data[$n]['alt'] = (string) ($alts[$n] ?? '');
                } elseif ($f['type'] === 'gallery') {
                    $k = 0;
                    foreach ($data[$n] as $i => $v) {
                        if ($v === self::PLACEHOLDER && isset($pending[$n][$k])) {
                            $d = $uploads->storeImage($pending[$n][$k]);
                            $created[] = $d['src'];
                            $d['alt'] = (string) ($alts[$n][$k] ?? '');
                            $data[$n][$i] = $d;
                            $k++;
                        }
                    }
                } else {
                    $data[$n] = $uploads->storePdf($pending[$n]);
                    $created[] = $data[$n]['url'];
                }
            } catch (UploadException $e) {
                $this->discard($created);
                $created = [];

                return [$n => $e->getMessage()];
            }
        }

        return [];
    }

    /**
     * Значения для показа в форме: введённое пользователем (если пришло) или сохранённое.
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $post
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function displayValues(array $def, array $post, array $item): array
    {
        $out = [];
        $submitted = $post !== [];
        foreach ($def['fields'] as $f) {
            $n = (string) $f['name'];
            if ($f['type'] === 'image') {
                $out[$n] = $item[$n] ?? null;
                $out[$n . '__alt'] = $submitted
                    ? $this->cleanAlt($post[$n . '__alt'] ?? '')
                    : (is_array($item[$n] ?? null) && is_string($item[$n]['alt'] ?? null) ? $item[$n]['alt'] : '');
            } elseif ($f['type'] === 'gallery') {
                $new = [];
                foreach ($submitted ? $post : [] as $key => $v) {
                    if (preg_match('/^' . preg_quote($n, '/') . '__newalt_(\d{1,3})$/', (string) $key, $m) === 1) {
                        $new[(int) $m[1]] = $this->cleanAlt($v);
                    }
                }
                ksort($new);
                $out[$n] = ['rows' => $this->galleryRows((array) ($item[$n] ?? []), $post, $n, $submitted), 'new' => $new];
            } elseif (!$submitted || $f['type'] === 'file' || !empty($f['computed'])) {
                $out[$n] = $item[$n] ?? null;
            } elseif ($f['type'] === 'bool') {
                $out[$n] = !empty($post[$n]) && $post[$n] !== '0';
            } else {
                $out[$n] = is_scalar($post[$n] ?? null) ? (string) $post[$n] : '';
            }
        }

        return $out;
    }

    // ---------- данные ----------

    /** @param array<string,string> $params */
    private function name(array $params): ?string
    {
        $name = $params['collection'] ?? '';

        return array_key_exists($name, Collections::all()) ? $name : null;
    }

    /** @param array<string,string> $params */
    private function id(array $params): ?string
    {
        $id = $params['id'] ?? '';

        return $id === 'new' || preg_match(self::ID, $id) === 1 ? $id : null;
    }

    /** @return array<string,mixed>|null */
    private function find(string $name, string $id): ?array
    {
        foreach ($this->app->store()->read($name)['items'] ?? [] as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Список в порядке показа: по полю order, если оно есть в схеме, иначе — как лежит в файле.
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @return list<array<string,mixed>>
     */
    private function ordered(string $name, array $def): array
    {
        $items = array_values($this->app->store()->read($name)['items'] ?? []);
        if (in_array('order', array_column($def['fields'], 'name'), true)) {
            usort($items, static fn (array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));
        }

        return $items;
    }

    /**
     * Читает, меняет и пишет список; при гонке с другим сохранением повторяет с новыми данными.
     * $change получает записи и возвращает новый список или null («менять нечего»).
     *
     * @param callable(list<array<string,mixed>>):(array<int,array<string,mixed>>|null) $change
     */
    private function mutate(string $name, callable $change): bool
    {
        $store = $this->app->store();
        for ($try = 0; $try < self::RETRIES; $try++) {
            $hash = $store->hash($name);
            $items = array_values($store->read($name)['items'] ?? []);
            $new = $change($items);
            if ($new === null) {
                return false;
            }
            try {
                $store->write($name, ['items' => array_values($new)], $this->app->actor(), $hash);

                return true;
            } catch (ConflictException) {
                continue;
            }
        }
        throw new \RuntimeException('Не удалось сохранить: данные меняют слишком часто. Повторите.');
    }

    /** @param list<array<string,mixed>> $items */
    private function newId(array $items): string
    {
        $taken = array_column($items, 'id');
        do {
            $id = bin2hex(random_bytes(6));
        } while (in_array($id, $taken, true));

        return $id;
    }

    /**
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $record
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function withOrderAtEnd(array $def, array $record, array $items): array
    {
        if (in_array('order', array_column($def['fields'], 'name'), true) && empty($record['order'])) {
            $record['order'] = ($items ? max(array_map(static fn (array $i): int => (int) ($i['order'] ?? 0), $items)) : 0) + 10;
        }

        return $record;
    }

    /**
     * @param array{fields:list<array<string,mixed>>} $def
     * @param list<array<string,mixed>> $items
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function uniqueSlug(array $def, array $items, array $data, string $selfId): array
    {
        if (!in_array('slug', array_column($def['fields'], 'name'), true) || ($data['slug'] ?? '') === '') {
            return [];
        }
        foreach ($items as $item) {
            if (($item['id'] ?? null) !== $selfId && ($item['slug'] ?? null) === $data['slug']) {
                return ['slug' => 'Такой адрес уже занят — измените адрес страницы'];
            }
        }

        return [];
    }

    /**
     * Допустимые slug для полей-ссылок.
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @return array<string,list<string>>
     */
    private function refSlugs(array $def): array
    {
        $out = [];
        foreach ($def['fields'] as $f) {
            if ($f['type'] === 'ref') {
                $ref = (string) $f['ref'];
                $out[$ref] = array_values(array_filter(array_column($this->app->store()->read($ref)['items'] ?? [], 'slug'), 'is_string'));
            }
        }

        return $out;
    }

    /**
     * @param array{fields:list<array<string,mixed>>} $def
     * @return array<string,array<string,string>>
     */
    private function refOptions(array $def): array
    {
        $out = [];
        foreach ($def['fields'] as $f) {
            if ($f['type'] !== 'ref') {
                continue;
            }
            $ref = (string) $f['ref'];
            $refDef = Collections::get($ref);
            foreach ($this->app->store()->read($ref)['items'] ?? [] as $item) {
                if (is_string($item['slug'] ?? null)) {
                    $out[$ref][$item['slug']] = $this->itemTitle($refDef, $item);
                }
            }
        }

        return $out;
    }

    // ---------- файлы ----------

    /**
     * Адреса загруженных файлов записи (описание фото → /uploads/img/{id}, PDF → его адрес).
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $item
     * @return list<string>
     */
    private function fileRefs(array $def, array $item): array
    {
        $refs = [];
        foreach ($def['fields'] as $f) {
            $v = $item[$f['name']] ?? null;
            if ($f['type'] === 'image' && is_array($v) && is_string($v['src'] ?? null)) {
                $refs[] = $v['src'];
            } elseif ($f['type'] === 'file' && is_array($v) && is_string($v['url'] ?? null)) {
                $refs[] = $v['url'];
            } elseif ($f['type'] === 'gallery' && is_array($v)) {
                foreach ($v as $photo) {
                    if (is_array($photo) && is_string($photo['src'] ?? null)) {
                        $refs[] = $photo['src'];
                    }
                }
            }
        }

        return $refs;
    }

    /**
     * Удаляет файлы, которые больше никому не нужны: ни текущим данным, ни одной из хранимых версий (иначе «Вернуть эту версию»
     * вернула бы битые ссылки). Заменённый файл поэтому живёт, пока его версия не выйдет из последних 20; потом его уберёт scripts/uploads-gc.php.
     * Отказ или сбой — не причина ломать ответ (файл останется лишним, данные верны).
     *
     * @param iterable<string> $urls
     */
    private function discard(iterable $urls): void
    {
        $urls = is_array($urls) ? $urls : iterator_to_array($urls, false);
        if ($urls === []) {
            return;
        }
        $uploads = $this->app->uploads();
        $refs = $this->app->keptUploadRefs();
        foreach ($urls as $url) {
            if ($uploads->isReferenced($url, $refs)) {
                continue;
            }
            try {
                $uploads->delete($url);
            } catch (\Throwable $e) {
                error_log('Groza uploads cleanup: ' . $e->getMessage());
            }
        }
    }

    // ---------- показ записей ----------

    /**
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $item
     */
    private function itemTitle(array $def, array $item): string
    {
        $names = array_column($def['fields'], 'name');
        foreach (self::TITLE_FIELDS as $key) {
            if (in_array($key, $names, true) && is_string($item[$key] ?? null) && trim($item[$key]) !== '') {
                $t = trim($item[$key]);

                return mb_strlen($t) > 90 ? mb_substr($t, 0, 90) . '…' : $t;
            }
        }

        return 'Без названия';
    }

    /**
     * Короткие факты под названием в списке. Смены — даты, сезон, цена; остальные — первые заполненные простые поля.
     *
     * @param array{fields:list<array<string,mixed>>} $def
     * @param array<string,mixed> $item
     * @return list<string>
     */
    private function facts(string $name, array $def, array $item): array
    {
        $facts = [];
        if ($name === 'shifts') {
            if (!empty($item['dateStart']) && !empty($item['dateEnd'])) {
                $facts[] = self::date((string) $item['dateStart']) . ' — ' . self::date((string) $item['dateEnd']);
            }
            if (!empty($item['season'])) {
                $facts[] = (string) $item['season'];
            }
            if (isset($item['price']) && is_int($item['price'])) {
                $facts[] = number_format($item['price'], 0, ',', ' ') . ' ₽';
            }

            return $facts;
        }
        $titleKey = null;
        foreach ($def['fields'] as $f) {
            $key = (string) $f['name'];
            if ($titleKey === null && in_array($key, self::TITLE_FIELDS, true)) {
                $titleKey = $key;
                continue;
            }
            $v = $item[$key] ?? null;
            if (count($facts) >= 2 || $v === null || $v === '' || !in_array($f['type'], ['text', 'enum', 'date'], true) || $key === 'slug') {
                continue;
            }
            $fact = match ($f['type']) {
                'enum' => (string) ($f['options'][$v] ?? $v),
                'date' => self::date((string) $v),
                default => (string) $v,
            };
            if (!in_array($fact, $facts, true) && mb_strtolower($fact) !== mb_strtolower($this->itemTitle($def, $item))) { // «Вожатый · Вожатый» не нужен
                $facts[] = $fact;
            }
        }
        // Документ: тип и размер файла («PDF, 1,4 МБ»)
        foreach ($def['fields'] as $f) {
            $file = $item[$f['name']] ?? null;
            if ($f['type'] === 'file' && is_array($file) && is_string($file['url'] ?? null)) {
                $facts[] = SvedeniyaPage::fileMeta($file['url'], (int) ($file['bytes'] ?? 0));
            }
        }

        return $facts;
    }

    /** Поле, которым запись скрывается с сайта: «hidden» или (у отзывов) «visible»; нет такого — null. @param array{fields:list<array<string,mixed>>} $def */
    private function hideField(array $def): ?string
    {
        $names = array_column($def['fields'], 'name');

        return in_array('hidden', $names, true) ? 'hidden' : (in_array('visible', $names, true) ? 'visible' : null);
    }

    /**
     * Счётчик над списком («На главной: 3 из 5»), если он описан в коллекции.
     *
     * @param array<string,mixed> $def
     * @param list<array<string,mixed>> $items
     */
    private function counter(array $def, array $items): ?string
    {
        $c = $def['counter'] ?? null;
        if (!is_array($c)) {
            return null;
        }
        $n = count(array_filter($items, static fn (array $i): bool => !empty($i[$c['field']])));

        return $c['label'] . ': ' . $n . ' из ' . $c['max'];
    }

    private static function date(string $iso): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);

        return $d ? $d->format('d.m.Y') : $iso;
    }

    /** Адрес страницы на сайте: {slug} заменяется адресом записи; нет адреса — ссылки нет. @param array<string,mixed> $item */
    private function siteUrl(string $pattern, array $item): ?string
    {
        if (!str_contains($pattern, '{slug}')) {
            return $pattern;
        }
        $slug = $item['slug'] ?? null;

        return is_string($slug) && $slug !== '' ? str_replace('{slug}', $slug, $pattern) : null;
    }
}
