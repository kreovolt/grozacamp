<?php
/**
 * Фото: показ текущего, выбор «оставить / убрать» и загрузка нового файла.
 * Готовое описание фото из формы не принимается — его делает сервер после загрузки.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;
use function Groza\View\image;

$name = (string) $f['name'];
$thumb = null;
try {
    $d = is_string($value) || is_array($value) ? image($value) : null;
    if ($d !== null && !empty($d['src']) && !empty($d['widths']) && !empty($d['formats'])) {
        $thumb = $d['src'] . '-' . min($d['widths']) . '.' . (in_array('jpg', $d['formats'], true) ? 'jpg' : $d['formats'][0]);
    }
} catch (\InvalidArgumentException) {
    $thumb = null;
}
$has = is_array($value) || (is_string($value) && $value !== '');
?>
<span class="label" id="<?= e($id) ?>-label"><?= e((string) $f['label']) ?><?php if (!empty($f['required'])): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></span>
<?php if ($has): ?>
<div class="current">
<?php if ($thumb !== null): ?>
  <img class="current__img" src="<?= e($thumb) ?>" alt="Сейчас выбрано" width="96" height="96">
<?php endif ?>
  <div class="choices choices--row">
    <label class="choice"><input type="radio" name="<?= e($name) ?>__action" value="keep" checked><span>Оставить</span></label>
    <label class="choice"><input type="radio" name="<?= e($name) ?>__action" value="remove"><span>Убрать</span></label>
  </div>
</div>
<?php endif ?>
<label class="file-pick" for="<?= e($id) ?>"><?= $has ? 'Заменить фото' : 'Выбрать фото' ?></label>
<input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp" <?= $aria ?>>
<p class="hint">JPEG, PNG или WebP до 10 МБ. С телефона можно сразу снять фото.</p>
