<?php
/**
 * PDF-файл: показ текущего, «оставить / убрать» и загрузка нового. Описание файла делает сервер.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 */
use function Groza\View\e;

$name = (string) $f['name'];
$has = is_array($value) && !empty($value['url']);
?>
<span class="label" id="<?= e($id) ?>-label"><?= e((string) $f['label']) ?><?php if (!empty($f['required'])): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></span>
<?php if ($has): ?>
<div class="current">
  <p class="current__file"><a href="<?= e((string) $value['url']) ?>" target="_blank" rel="noopener"><?= e((string) ($value['originalName'] ?? 'Открыть файл')) ?></a><?php if (!empty($value['bytes'])): ?> · <?= e(number_format(((int) $value['bytes']) / 1048576, 1, ',', '')) ?> МБ<?php endif ?></p>
  <div class="choices choices--row">
    <label class="choice"><input type="radio" name="<?= e($name) ?>__action" value="keep" checked><span>Оставить</span></label>
    <label class="choice"><input type="radio" name="<?= e($name) ?>__action" value="remove"><span>Убрать</span></label>
  </div>
</div>
<?php endif ?>
<label class="file-pick" for="<?= e($id) ?>"><?= $has ? 'Заменить файл' : 'Выбрать файл' ?></label>
<input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" accept="application/pdf,.pdf" <?= $aria ?>>
<p class="hint">PDF до 20 МБ.</p>
