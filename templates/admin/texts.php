<?php
declare(strict_types=1);
/**
 * Тексты сайта: одна группа — одна форма.
 * @var string $group
 * @var array<string,string> $groups
 * @var list<array<string,mixed>> $fields
 * @var list<array{id:string,label:string,message:string}> $summary
 * @var string|null $banner
 * @var bool $saved
 * @var string $hash
 * @var string $basedOn
 * @var bool $unsaved
 * @var bool $editor есть ли в группе текст с оформлением (тогда подключается Trix)
 * @var string $conflictText
 * @var string $siteUrl
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$title = 'Тексты: ' . $groups[$group];
$this->layout('admin/layout', ['title' => $title, 'user' => $user, 'csrf' => $csrf, 'editor' => $editor]);
?>
<p class="back back-top"><a href="/admin/">← К разделам</a></p>
<h1 class="h1"><?= e($groups[$group]) ?></h1>
<nav class="tabs" aria-label="Группы текстов">
<?php foreach ($groups as $slug => $label): ?>
  <a class="tab" href="/admin/texts/<?= e($slug) ?>/"<?= $slug === $group ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach ?>
</nav>
<?php if ($saved): ?>
<p class="alert alert-ok" role="status">Сохранено · <a href="<?= e($siteUrl) ?>" target="_blank" rel="noopener">Посмотреть на сайте</a></p>
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
  <span data-draft-text data-existing="Восстановлен черновик. Пока вас не было, тексты могли изменить — проверьте перед сохранением.">Восстановлен черновик</span>
  <button type="button" class="btn btn-ghost" data-draft-cancel>Отменить</button>
</div>
<p class="alert alert-error" role="alert" data-draft-banner="conflict" hidden><?= e($conflictText) ?></p>
<p class="alert alert-error" role="alert" data-draft-banner="changed" hidden>Тексты изменили после того, как вы начали правку — сравните и сохраните ещё раз.</p>
<form method="post" action="/admin/texts/<?= e($group) ?>/" class="form edit-form" data-draft data-based-on="<?= e($basedOn) ?>"<?= $banner !== null ? ' data-draft-conflict' : '' ?><?= $unsaved ? ' data-draft-unsaved' : '' ?> novalidate autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="_hash" value="<?= e($hash) ?>">
<?php foreach ($fields as $f):
    $id = 't-' . $f['key'];
    $name = 'text[' . $f['key'] . ']';
    $invalid = $f['error'] !== null || $f['confirm'];
    $aria = $invalid ? ' aria-invalid="true"' : '';
    ?>
  <div class="field text-field<?= $invalid ? ' has-error' : '' ?>" id="<?= e($f['key']) ?>">
    <label for="<?= e($id) ?>"><?= e($f['label']) ?><?php if ($f['required']): ?> <span class="req" aria-hidden="true">*</span><?php endif ?></label>
<?php if ($f['code'] !== null): ?>
    <p class="hint">Основание: п. <?= e((string) $f['code']) ?> приказа № 201<?= $f['required'] ? ' — обязательный пункт для проверки' : '' ?>.</p>
<?php endif ?>
<?php if ($f['legalFull'] !== null): ?>
    <details class="legal">
      <summary>Что требует приказ</summary>
      <p><?= e((string) $f['legalTitle']) ?>: <?= e((string) $f['legalFull']) ?></p>
    </details>
<?php endif ?>
<?php if ($f['type'] === 'richtext'): ?>
    <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="12" data-richtext<?= $aria ?>><?= e((string) $f['value']) ?></textarea>
<?php elseif ($f['type'] === 'list'): ?>
    <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="8"<?= $aria ?> aria-describedby="<?= e($id) ?>-hint"><?= e((string) $f['value']) ?></textarea>
    <p class="hint" id="<?= e($id) ?>-hint">Один пункт — одна строка.</p>
<?php elseif ($f['multiline']): ?>
    <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="3"<?= $aria ?>><?= e((string) $f['value']) ?></textarea>
<?php else: ?>
    <input type="text" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) $f['value']) ?>"<?= $aria ?>>
<?php endif ?>
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
