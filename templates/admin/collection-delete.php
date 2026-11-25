<?php
/**
 * Подтверждение удаления: само удаление — только POST с токеном.
 * @var string $collection
 * @var array{label:string} $def
 * @var string $id
 * @var string $heading
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Удалить?', 'user' => $user, 'csrf' => $csrf]);
?>
<section class="card" aria-labelledby="del-title">
  <h1 id="del-title" class="h1">Удалить запись?</h1>
  <p class="del-name">«<?= e($heading) ?>»</p>
  <p>Раздел «<?= e($def['label']) ?>». Запись исчезнет и с сайта. Вернуть её будет нельзя.</p>
  <form method="post" action="/admin/<?= e($collection) ?>/<?= e($id) ?>/delete/" class="actions">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="confirm" value="1">
    <button type="submit" class="btn btn-danger">Да, удалить</button>
    <a class="btn btn-ghost" href="/admin/<?= e($collection) ?>/<?= $id !== '' ? e($id) . '/' : '' ?>">Нет, оставить</a>
  </form>
</section>
