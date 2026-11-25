<?php
/**
 * Список записей раздела. У смен — крупные кнопки статуса (одно нажатие).
 * @var string $collection
 * @var array{label:string} $def
 * @var list<array{id:string,title:string,facts:list<string>,hidden:bool,status:?string,first:bool,last:bool}> $rows
 * @var bool $orderable
 * @var array<string,string>|null $statusOptions
 * @var string|null $flash
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => $def['label'], 'user' => $user, 'csrf' => $csrf]);
?>
<p class="back back-top"><a href="/admin/">← Разделы</a></p>
<div class="page-head">
  <h1 class="h1"><?= e($def['label']) ?></h1>
  <a class="btn btn-primary" href="/admin/<?= e($collection) ?>/new/">Добавить</a>
</div>
<?php if ($flash !== null): ?>
<p class="alert alert-ok" role="status"><?= e($flash) ?></p>
<?php endif ?>
<?php if ($rows === []): ?>
<p class="card empty">Здесь пока ничего нет. Нажмите «Добавить».</p>
<?php endif ?>
<ul class="rows">
<?php foreach ($rows as $row): ?>
  <li class="row<?= $row['hidden'] ? ' row--hidden' : '' ?>" id="row-<?= e($row['id']) ?>">
    <div class="row__main">
      <h2 class="row__title"><a href="/admin/<?= e($collection) ?>/<?= e($row['id']) ?>/"><?= e($row['title']) ?></a><?php if ($row['hidden']): ?> <span class="badge">Скрыто</span><?php endif ?></h2>
<?php if ($row['facts'] !== []): ?>
      <p class="row__facts"><?= e(implode(' · ', $row['facts'])) ?></p>
<?php endif ?>
    </div>
<?php if ($statusOptions !== null): ?>
    <form method="post" action="/admin/<?= e($collection) ?>/<?= e($row['id']) ?>/status/" class="status" aria-label="Статус смены «<?= e($row['title']) ?>»">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php foreach ($statusOptions as $key => $label): ?>
      <button type="submit" name="status" value="<?= e((string) $key) ?>" class="status__btn status__btn--<?= e((string) $key) ?>" aria-pressed="<?= $row['status'] === (string) $key ? 'true' : 'false' ?>"><?= e((string) $label) ?></button>
<?php endforeach ?>
    </form>
<?php endif ?>
    <div class="row__tools">
      <a class="btn btn-ghost" href="/admin/<?= e($collection) ?>/<?= e($row['id']) ?>/">Изменить</a>
<?php if ($orderable): ?>
      <form method="post" action="/admin/<?= e($collection) ?>/<?= e($row['id']) ?>/move/" class="move">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button type="submit" name="dir" value="up" class="btn btn-icon" aria-label="Выше: <?= e($row['title']) ?>"<?= $row['first'] ? ' disabled' : '' ?>>↑</button>
        <button type="submit" name="dir" value="down" class="btn btn-icon" aria-label="Ниже: <?= e($row['title']) ?>"<?= $row['last'] ? ' disabled' : '' ?>>↓</button>
      </form>
<?php endif ?>
      <a class="btn btn-ghost btn-quiet" href="/admin/<?= e($collection) ?>/<?= e($row['id']) ?>/delete/">Удалить</a>
    </div>
  </li>
<?php endforeach ?>
</ul>
