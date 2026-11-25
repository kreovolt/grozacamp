<?php
/**
 * Страница 413: запрос больше, чем принимает сервер (обычно слишком тяжёлое фото).
 */
$this->layout('admin/layout', ['title' => 'Файл слишком большой', 'user' => null, 'csrf' => '']);
?>
<section class="card card-narrow" aria-labelledby="big-title">
  <h1 id="big-title" class="h1">Файл не принят</h1>
  <p class="alert alert-error" role="alert">Файл слишком большой для сервера. Уменьшите фото (до 10 МБ) или PDF (до 20 МБ) и попробуйте снова.</p>
  <p><a class="btn btn-primary" href="/admin/">Вернуться в админку</a></p>
</section>
