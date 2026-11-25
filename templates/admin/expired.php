<?php
/**
 * Страница 419: токен CSRF не подошёл (сессия истекла или запрос чужой).
 */
$this->layout('admin/layout', ['title' => 'Сессия устарела', 'user' => null, 'csrf' => '']);
?>
<section class="card card-narrow" aria-labelledby="exp-title">
  <h1 id="exp-title" class="h1">Нужно обновить страницу</h1>
  <p class="alert alert-error" role="alert">Сессия устарела. Обновите страницу и повторите.</p>
  <p><a class="btn btn-primary" href="/admin/">Вернуться в админку</a></p>
</section>
