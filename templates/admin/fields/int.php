<?php
declare(strict_types=1);
/**
 * Целое число: текстовое поле с цифровой клавиатурой (пробелы в тысячах допускаются).
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;

echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<input type="text" inputmode="numeric" id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" value="<?= e(is_scalar($value) ? (string) $value : '') ?>" autocomplete="off" <?= $aria ?>>
