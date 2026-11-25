<?php
/**
 * Выбор из списка: до четырёх вариантов — крупные радиокнопки, больше — выпадающий список.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 * @var array<string,string> $options
 */
use Groza\Admin\FormRenderer;

use function Groza\View\e;

$current = is_scalar($value) ? (string) $value : '';
$name = (string) $f['name'];
if (count($options) <= FormRenderer::RADIO_MAX):
?>
<fieldset class="choices" <?= $aria ?>>
  <legend><?= e((string) $f['label']) ?><?php if (!empty($f['required'])): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></legend>
<?php $i = 0; foreach ($options as $key => $label): $i++; ?>
  <label class="choice"><input type="radio" name="<?= e($name) ?>" value="<?= e((string) $key) ?>"<?= $i === 1 ? ' id="' . e($id) . '"' : '' ?><?= $current === (string) $key ? ' checked' : '' ?>><span><?= e((string) $label) ?></span></label>
<?php endforeach ?>
</fieldset>
<?php else:
    echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<select id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $aria ?>>
<?php if (empty($f['required']) || $current === ''): ?>
  <option value="">— не выбрано —</option>
<?php endif ?>
<?php foreach ($options as $key => $label): ?>
  <option value="<?= e((string) $key) ?>"<?= $current === (string) $key ? ' selected' : '' ?>><?= e((string) $label) ?></option>
<?php endforeach ?>
</select>
<?php endif ?>
