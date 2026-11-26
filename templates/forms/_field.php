<?php
declare(strict_types=1);

/**
 * Одно поле формы: подпись, элемент, подсказка, текст ошибки (id связан через aria-describedby).
 * @var string $formId   booking | vacancy (префикс id)
 * @var string $name
 * @var string $label
 * @var string|null $type        text | tel | email | textarea | select | checkbox
 * @var string $value
 * @var string|null $error
 * @var bool|null $required
 * @var string|null $hint
 * @var string|null $autocomplete
 * @var string|null $inputmode
 * @var int|null $maxlength
 * @var list<array{0:string,1:string}>|null $choices   для select: [значение, подпись]
 * @var string|null $labelHtml   для checkbox: готовая разметка подписи (со ссылкой)
 */
use function Groza\View\e;
use function Groza\View\t;
use function Groza\View\typeset;

$type ??= 'text';
$id = $formId . '-' . $name;
$errorId = $id . '-error';
$hintId = $id . '-hint';
$error = (string) ($error ?? '');
$value = (string) ($value ?? '');
$describedBy = trim(($error !== '' ? $errorId : '') . ' ' . (!empty($hint) ? $hintId : ''));
$control = 'block w-full rounded-btn border-2 bg-paper px-4 py-3 text-base text-ink placeholder:text-ink-soft/70 focus:border-forest focus:outline-none focus-visible:ring-2 focus-visible:ring-gold '
    . ($error !== '' ? 'border-rust-deep' : 'border-forest/30');
$attrs = ' id="' . e($id) . '" name="' . e($name) . '"'
    . (!empty($required) ? ' required' : '')
    . ($error !== '' ? ' aria-invalid="true"' : '')
    . ($describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '')
    . (!empty($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '')
    . (!empty($inputmode) ? ' inputmode="' . e($inputmode) . '"' : '')
    . (!empty($maxlength) ? ' maxlength="' . (int) $maxlength . '"' : '');
?>
<div class="field" data-field="<?= e($name) ?>">
<?php if ($type === 'checkbox'): ?>
  <div class="flex items-start gap-3">
    <input class="mt-1 size-5 shrink-0 accent-rust-dark" type="checkbox" value="yes"<?= $attrs ?><?= $value === 'yes' ? ' checked' : '' ?>>
    <label class="tap text-[0.9375rem] leading-snug text-ink" for="<?= e($id) ?>"><?= isset($labelHtml) ? typeset($labelHtml) : t($label) ?></label>
  </div>
<?php else: ?>
  <label class="block font-semibold text-ink" for="<?= e($id) ?>"><?= t($label) ?><?php if (empty($required)): ?> <span class="font-normal text-ink-soft">(необязательно)</span><?php endif ?></label>
  <div class="mt-1.5">
  <?php if ($type === 'textarea'): ?>
    <textarea class="<?= e($control) ?>" rows="4"<?= $attrs ?>><?= e($value) ?></textarea>
  <?php elseif ($type === 'select'): ?>
    <select class="<?= e($control) ?>"<?= $attrs ?>>
      <?php foreach ($choices ?? [] as [$optValue, $optLabel]): ?>
        <option value="<?= e($optValue) ?>"<?= $optValue === $value ? ' selected' : '' ?>><?= e($optLabel) ?></option>
      <?php endforeach ?>
    </select>
  <?php else: ?>
    <input class="<?= e($control) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>"<?= $attrs ?>>
  <?php endif ?>
  </div>
<?php endif ?>
<?php if (!empty($hint)): ?>
  <p class="mt-1.5 text-sm text-ink-soft" id="<?= e($hintId) ?>"><?= t($hint) ?></p>
<?php endif ?>
  <p class="mt-1.5 text-sm font-semibold text-rust-deep" id="<?= e($errorId) ?>" data-error<?= $error === '' ? ' hidden' : '' ?>><?= t($error) ?></p>
</div>
