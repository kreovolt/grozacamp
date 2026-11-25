<?php
declare(strict_types=1);
/**
 * Подпись поля с отметкой обязательности (или «необязательно», если поле можно оставить пустым и пока оно пусто).
 * @var array<string,mixed> $f
 * @var string $id
 * @var bool $mark показать «необязательно»
 */
use function Groza\View\e;
?>
<label for="<?= e($id) ?>"><?= e((string) $f['label']) ?><?php if (!empty($f['required'])): ?> <span class="req" aria-hidden="true">*</span><?php elseif (!empty($mark)): ?> <span class="opt">необязательно</span><?php endif ?></label>
