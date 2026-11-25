<?php
/**
 * Вычисляемое поле: только показывается, в запросе не участвует.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 */
use function Groza\View\e;
?>
<span class="label" id="<?= e($id) ?>-label"><?= e((string) $f['label']) ?></span>
<output class="readonly" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-label"><?= is_scalar($value) && (string) $value !== '' ? e((string) $value) : '—' ?></output>
