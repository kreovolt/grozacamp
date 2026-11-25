<?php
declare(strict_types=1);

namespace Groza\Admin;

use Groza\View\View;

/**
 * Форма записи по описанию коллекции (Collections): подписи, подсказки, обязательность, счётчик знаков,
 * поле нужного вида на каждый тип. Возвращает только поля — тег <form>, токены и кнопки добавляет шаблон страницы.
 *
 * Поля именуются как в схеме (name="title"). Файловые поля: загрузка — <input type=file name="photo">,
 * выбор «оставить / убрать» — name="photo__action". Готовое описание файла из формы никогда не принимается:
 * его делает сервер (Uploads).
 */
final class FormRenderer
{
    /** До скольких вариантов список показывается крупными радиокнопками (удобно пальцем), дальше — выпадающий список. */
    public const RADIO_MAX = 4;

    /** @param array<string,array<string,string>> $refOptions коллекция → [slug => подпись] для полей-ссылок */
    public function __construct(private View $view, private array $refOptions = [])
    {
    }

    /**
     * @param array{fields:list<array<string,mixed>>} $collectionDef
     * @param array<string,mixed> $values что показать в полях (сохранённое или введённое)
     * @param array<string,string> $errors поле → сообщение
     */
    public function render(array $collectionDef, array $values, array $errors): string
    {
        $html = '';
        foreach ($collectionDef['fields'] as $field) {
            $html .= $this->heading($field)
                . $this->field($field, $values[$field['name']] ?? null, $errors[$field['name']] ?? null, $values, $errors);
        }

        return $html;
    }

    /** Заголовок группы полей (например, «Доступная среда (п. 11)») перед первым полем группы. @param array<string,mixed> $f */
    private function heading(array $f): string
    {
        $h = $f['heading'] ?? null;
        if (!is_array($h)) {
            return '';
        }
        $id = 'h-' . $this->e((string) $f['name']);

        return '<h2 class="form-heading" id="' . $id . '">' . $this->e((string) $h['title']) . '</h2>'
            . (isset($h['help']) ? '<p class="hint form-heading__hint">' . $this->e((string) $h['help']) . '</p>' : '') . "
";
    }

    /**
     * @param array<string,mixed> $f
     * @param array<string,mixed> $values все значения формы (подпись к фото лежит рядом: «photo__alt»)
     * @param array<string,string> $errors
     */
    private function field(array $f, mixed $value, ?string $error, array $values = [], array $errors = []): string
    {
        $name = (string) $f['name'];
        $type = (string) $f['type'];
        $id = 'f-' . $name;
        $help = isset($f['help']) ? (string) $f['help'] : '';
        $describedBy = trim(($help !== '' ? $id . '-help ' : '') . ($error !== null ? $id . '-error' : ''));
        $aria = ($error !== null ? 'aria-invalid="true" ' : '')
            . (!empty($f['required']) ? 'aria-required="true" ' : '')
            . ($describedBy !== '' ? 'aria-describedby="' . htmlspecialchars($describedBy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" ' : '');
        $vars = [
            'f' => $f,
            'id' => $id,
            'value' => $value,
            'error' => $error,
            'alt' => is_scalar($values[$name . '__alt'] ?? null) ? (string) $values[$name . '__alt'] : '',
            'altError' => $errors[$name . '__alt'] ?? null,
            'aria' => trim($aria),
            'options' => $f['options'] ?? [],
            'refOptions' => isset($f['ref']) ? ($this->refOptions[(string) $f['ref']] ?? []) : [],
        ];

        if (!empty($f['computed'])) {
            $inner = $this->view->partial('admin/fields/_computed', $vars);
        } elseif ($type === 'gallery') {
            $inner = $this->view->partial('admin/fields/_gallery', $vars);
        } else {
            $inner = $this->view->partial('admin/fields/' . $type, $vars);
        }

        $counter = '';
        if (isset($f['max']) && in_array($type, ['text', 'textarea'], true) && empty($f['computed'])) {
            $len = is_scalar($value) ? mb_strlen((string) $value) : 0;
            $counter = '<p class="count" id="' . $id . '-count" data-count-for="' . $id . '" data-max="' . (int) $f['max'] . '">'
                . $len . ' из ' . (int) $f['max'] . '</p>';
        }

        $legal = isset($f['legal'])
            ? '<details class="legal"><summary>Что сказано в приказе № 201</summary><p>' . $this->e((string) $f['legal']) . '</p></details>'
            : '';
        $hasError = $error !== null || isset($errors[$name . '__alt']);

        return '<div class="field field--' . $this->e($type) . ($hasError ? ' has-error' : '') . '" data-field="' . $this->e($name) . '">'
            . $inner
            . $counter
            . $legal
            . ($help !== '' ? '<p class="hint" id="' . $id . '-help">' . $this->e($help) . '</p>' : '')
            . ($error !== null ? '<p class="field-error" id="' . $id . '-error">' . $this->e($error) . '</p>' : '')
            . '</div>' . "\n";
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
