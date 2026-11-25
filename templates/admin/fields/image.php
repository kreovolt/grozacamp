<?php
declare(strict_types=1);
/**
 * Фото: показ текущего, выбор «оставить / убрать», загрузка нового файла и обязательная подпись «Что на фото?».
 * Готовое описание фото из формы не принимается — его делает сервер после загрузки; подпись берётся из поля ниже.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 * @var string $alt
 * @var string|null $altError
 */
use function Groza\View\e;

$name = (string) $f['name'];
$has = is_array($value) || (is_string($value) && $value !== '');
$altId = $id . '__alt';
?>
<span class="label" id="<?= e($id) ?>-label"><?= e((string) $f['label']) ?><?php if (!empty($f['required'])): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></span>
<?php if ($has): ?>
<div class="current">
  <?= $this->partial('admin/fields/_thumb', ['value' => $value, 'class' => 'current__img']) ?>
  <div class="choices choices--row">
    <label class="choice"><input type="radio" name="<?= e($name) ?>__action" value="keep" checked><span>Оставить</span></label>
    <label class="choice"><input type="radio" name="<?= e($name) ?>__action" value="remove"><span>Убрать</span></label>
  </div>
</div>
<?php endif ?>
<label class="file-pick" for="<?= e($id) ?>"><?= $has ? 'Заменить фото' : 'Выбрать фото' ?></label>
<input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp" <?= $aria ?>>
<p class="hint">JPEG, PNG или WebP до 10 МБ. С телефона можно сразу снять фото.</p>
<div class="alt-field">
  <label for="<?= e($altId) ?>">Что на фото?</label>
  <input type="text" id="<?= e($altId) ?>" name="<?= e($name) ?>__alt" value="<?= e($alt) ?>" maxlength="200" autocomplete="off"<?= $altError !== null ? ' aria-invalid="true" aria-describedby="' . e($altId) . '-error"' : '' ?>>
  <p class="hint">Одной фразой: кто и что на снимке. Нужно, когда загружаете своё фото: эту подпись читают незрячим посетителям.</p>
<?php if ($altError !== null): ?>
  <p class="field-error" id="<?= e($altId) ?>-error"><?= e($altError) ?></p>
<?php endif ?>
</div>
