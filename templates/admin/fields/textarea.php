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
<textarea id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" rows="5"<?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?> <?= $aria ?>><?= e(is_scalar($value) ? (string) $value : '') ?></textarea>
