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
            $html .= $this->field($field, $values[$field['name']] ?? null, $errors[$field['name']] ?? null);
        }

        return $html;
    }

    /** @param array<string,mixed> $f */
    private function field(array $f, mixed $value, ?string $error): string
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

        return '<div class="field field--' . $this->e($type) . ($error !== null ? ' has-error' : '') . '" data-field="' . $this->e($name) . '">'
            . $inner
            . $counter
            . ($help !== '' ? '<p class="hint" id="' . $id . '-help">' . $this->e($help) . '</p>' : '')
            . ($error !== null ? '<p class="field-error" id="' . $id . '-error">' . $this->e($error) . '</p>' : '')
            . '</div>' . "\n";
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
