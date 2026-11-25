<?php
/**
 * Галерея: пока только показывается (сохранённые фото при правке остаются как были).
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 */
use function Groza\View\e;

$count = is_array($value) ? count($value) : 0;
?>
<span class="label" id="<?= e($id) ?>-label"><?= e((string) $f['label']) ?></span>
<output class="readonly" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-label"><?= $count ?> фото. Состав галереи здесь пока не меняется.</output>
