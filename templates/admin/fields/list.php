<?php
declare(strict_types=1);
/**
 * Список строк: один пункт на строке.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;

$text = is_array($value) ? implode("\n", array_map('strval', array_filter($value, 'is_scalar'))) : (is_scalar($value) ? (string) $value : '');
echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<textarea id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" rows="6" <?= $aria ?>><?= e($text) ?></textarea>
<p class="hint">Один пункт на строке.</p>
