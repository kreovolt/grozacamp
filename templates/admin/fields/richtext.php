<?php
declare(strict_types=1);
/**
 * Текст с оформлением. Обычное многострочное поле с тем же именем: скрипт admin-editor.js превращает его в редактор Trix
 * (без скрипта поле остаётся рабочим — можно править как HTML). Старый текст без тегов показывается абзацами.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use Groza\Admin\Sanitizer;

use function Groza\View\e;

echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<textarea id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" rows="12" data-richtext <?= $aria ?>><?= e(is_string($value) ? Sanitizer::clean($value) : '') ?></textarea>
