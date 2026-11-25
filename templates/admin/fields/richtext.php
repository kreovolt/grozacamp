<?php
/**
 * Текст с оформлением. Пока — обычное многострочное поле с тем же именем (редактор подключается отдельно и берёт data-richtext).
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;

echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<textarea id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" rows="12" data-richtext <?= $aria ?>><?= e(is_scalar($value) ? (string) $value : '') ?></textarea>
