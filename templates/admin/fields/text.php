<?php
/**
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;

echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<input type="text" id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" value="<?= e(is_scalar($value) ? (string) $value : '') ?>"<?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?> autocomplete="off" <?= $aria ?>>
