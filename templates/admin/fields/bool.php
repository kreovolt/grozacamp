<?php
declare(strict_types=1);
/**
 * Переключатель «да / нет».
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;
?>
<label class="switch" for="<?= e($id) ?>">
  <input type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" value="1"<?= !empty($value) && $value !== '0' ? ' checked' : '' ?> <?= $aria ?>>
  <span class="switch__track" aria-hidden="true"></span>
  <span class="switch__text"><?= e((string) $f['label']) ?></span>
</label>
