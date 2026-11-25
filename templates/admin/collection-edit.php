<?php
declare(strict_types=1);
/**
 * Страница правки записи. Поля строит FormRenderer по описанию коллекции.
 * @var string $collection
 * @var array{label:string,fields:list<array<string,mixed>>} $def
 * @var string $id
 * @var bool $isNew
 * @var string $heading
 * @var string $form
 * @var string $hash
 * @var list<array{id:string,label:string,message:string}> $summary
 * @var string|null $banner
 * @var bool $saved
 * @var bool $hasFiles
 * @var bool $hasRichtext
 * @var bool $unsaved
 * @var string $basedOn
 * @var string $conflictText
 * @var string|null $siteUrl
 * @var string|null $sitePattern
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => $isNew ? 'Новая запись — ' . $def['label'] : $heading, 'user' => $user, 'csrf' => $csrf, 'editor' => $hasRichtext]);
$action = '/admin/' . $collection . '/' . $id . '/';
?>
<p class="back back-top"><a href="/admin/<?= e($collection) ?>/">← <?= e($def['label']) ?></a></p>
<h1 class="h1"><?= e($heading) ?></h1>
<?php if ($saved): ?>
<p class="alert alert-ok" role="status">Сохранено<?php if ($siteUrl !== null): ?> · <a href="<?= e($siteUrl) ?>" target="_blank" rel="noopener">Посмотреть на сайте</a><?php endif ?></p>
<?php endif ?>
<?php if ($banner !== null): ?>
<p class="alert alert-error" role="alert" id="form-banner" tabindex="-1"><?= e($banner) ?></p>
<?php endif ?>
<?php if ($hasFiles && ($banner !== null || $summary !== [])): ?>
<p class="hint">Выбирали фото или файл? Выберите файл снова: браузер не хранит выбранные файлы после ошибки.</p>
<?php endif ?>
<?php if ($summary !== []): ?>
<div class="alert alert-error summary" role="alert" id="error-summary" tabindex="-1">
  <p class="summary__title">Не получилось сохранить. Исправьте:</p>
  <ul>
<?php foreach ($summary as $s): ?>
    <li><a href="#<?= e($s['id']) ?>"><?= e($s['label']) ?></a> — <?= e($s['message']) ?></li>
<?php endforeach ?>
  </ul>
</div>
<?php endif ?>
<div class="where card-soft">
  <p class="where__title">Где на сайте</p>
<?php if ($sitePattern !== null): ?>
  <p class="where__path"><?= e($sitePattern) ?></p>
<?php if ($siteUrl !== null): ?>
  <p><a href="<?= e($siteUrl) ?>" target="_blank" rel="noopener">Посмотреть на сайте</a></p>
<?php else: ?>
  <p class="hint">Ссылка появится после первого сохранения.</p>
<?php endif ?>
<?php else: ?>
  <p class="hint">Отдельной страницы нет.</p>
<?php endif ?>
</div>
<div class="draft-notice alert alert-note" role="status" data-draft-notice hidden>
  <span data-draft-text data-existing="Восстановлен черновик. Пока вас не было, запись могли изменить — проверьте перед сохранением.">Восстановлен черновик</span>
  <button type="button" class="btn btn-ghost" data-draft-cancel>Отменить</button>
</div>
<p class="alert alert-error" role="alert" data-draft-banner="conflict" hidden><?= e($conflictText) ?></p>
<p class="alert alert-error" role="alert" data-draft-banner="changed" hidden>Запись изменили после того, как вы начали правку — сравните и сохраните ещё раз.</p>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form edit-form" data-draft data-based-on="<?= e($basedOn) ?>"<?= $banner !== null ? ' data-draft-conflict' : '' ?><?= $unsaved ? ' data-draft-unsaved' : '' ?> novalidate autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="_hash" value="<?= e($hash) ?>">
  <p class="hint"><span class="req" aria-hidden="true">*</span> — обязательное поле</p>
<?= $form ?>
  <div class="actions actions--sticky">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a class="btn btn-ghost" href="/admin/<?= e($collection) ?>/">Закрыть</a>
<?php if (!$isNew): ?>
    <a class="btn btn-ghost btn-quiet" href="/admin/<?= e($collection) ?>/<?= e($id) ?>/delete/">Удалить</a>
<?php endif ?>
  </div>
</form>
