<?php
declare(strict_types=1);
/**
 * Настройки сайта: контакты, часы, соцсети, получатель заявок, счётчик.
 * @var list<array<string,mixed>> $fields
 * @var list<array{id:string,label:string,message:string}> $summary
 * @var string|null $banner
 * @var bool $saved
 * @var string $hash
 * @var string $basedOn
 * @var bool $unsaved
 * @var string $conflictText
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Настройки', 'user' => $user, 'csrf' => $csrf]);
?>
<p class="back back-top"><a href="/admin/">← К разделам</a></p>
<h1 class="h1">Настройки</h1>
<?php if ($saved): ?>
<p class="alert alert-ok" role="status">Сохранено · <a href="/contacts/" target="_blank" rel="noopener">Посмотреть на сайте</a></p>
<?php endif ?>
<?php if ($banner !== null): ?>
<p class="alert alert-error" role="alert" id="form-banner" tabindex="-1"><?= e($banner) ?></p>
<?php endif ?>
<?php if ($summary !== []): ?>
<div class="alert alert-error summary" role="alert" id="error-summary" tabindex="-1">
  <p class="summary__title">Не сохранено. Проверьте:</p>
  <ul>
<?php foreach ($summary as $s): ?>
    <li><a href="#<?= e($s['id']) ?>"><?= e($s['label']) ?></a> — <?= e($s['message']) ?></li>
<?php endforeach ?>
  </ul>
</div>
<?php endif ?>
<div class="draft-notice alert alert-note" role="status" data-draft-notice hidden>
  <span data-draft-text data-existing="Восстановлен черновик. Пока вас не было, настройки могли изменить — проверьте перед сохранением.">Восстановлен черновик</span>
  <button type="button" class="btn btn-ghost" data-draft-cancel>Отменить</button>
</div>
<p class="alert alert-error" role="alert" data-draft-banner="conflict" hidden><?= e($conflictText) ?></p>
<p class="alert alert-error" role="alert" data-draft-banner="changed" hidden>Настройки изменили после того, как вы начали правку — сравните и сохраните ещё раз.</p>
<form method="post" action="/admin/settings/" class="form edit-form" data-draft data-based-on="<?= e($basedOn) ?>"<?= $banner !== null ? ' data-draft-conflict' : '' ?><?= $unsaved ? ' data-draft-unsaved' : '' ?> novalidate autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="_hash" value="<?= e($hash) ?>">
<?php $last = null;
foreach ($fields as $f):
    $id = 's-' . $f['key'];
    $invalid = $f['error'] !== null || $f['confirm'];
    $inputmode = ['phone' => 'tel', 'email' => 'email', 'url' => 'url', 'digits' => 'numeric'][$f['kind']] ?? 'text';
    $type = ['phone' => 'tel', 'email' => 'email', 'url' => 'url'][$f['kind']] ?? 'text';
    if ($f['group'] !== $last):
        $last = $f['group']; ?>
  <h2 class="form-heading"><?= e((string) $f['group']) ?></h2>
<?php endif ?>
  <div class="field<?= $invalid ? ' has-error' : '' ?>" id="<?= e($f['key']) ?>">
    <label for="<?= e($id) ?>"><?= e((string) $f['label']) ?><?php if ($f['code'] !== null): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></label>
    <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="settings[<?= e($f['key']) ?>]" value="<?= e((string) $f['value']) ?>" inputmode="<?= e($inputmode) ?>" maxlength="<?= (int) $f['max'] + 40 ?>" aria-describedby="<?= e($id) ?>-hint"<?= $invalid ? ' aria-invalid="true"' : '' ?>>
    <p class="hint" id="<?= e($id) ?>-hint"><?= e((string) $f['hint']) ?><?php if ($f['code'] !== null): ?> Основание: п. <?= e((string) $f['code']) ?> приказа № 201, обязательный пункт.<?php endif ?></p>
<?php if ($f['error'] !== null): ?>
    <p class="field-error"><?= e((string) $f['error']) ?></p>
<?php endif ?>
<?php if ($f['confirm'] && $f['warning'] !== null): ?>
    <div class="confirm-empty">
      <p class="field-error"><?= e((string) $f['warning']) ?></p>
      <label class="choice choice--check"><input type="checkbox" name="confirm_empty[<?= e($f['key']) ?>]" value="1"> Да, сохранить пустым</label>
    </div>
<?php endif ?>
  </div>
<?php endforeach ?>
  <div class="actions actions--sticky">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a class="btn btn-ghost" href="/admin/">Закрыть</a>
  </div>
</form>
