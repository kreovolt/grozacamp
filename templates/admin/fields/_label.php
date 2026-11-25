<?php
/**
 * Подпись поля с отметкой обязательности.
 * @var array<string,mixed> $f
 * @var string $id
 */
use function Groza\View\e;
?>
<label for="<?= e($id) ?>"><?= e((string) $f['label']) ?><?php if (!empty($f['required'])): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></label>
