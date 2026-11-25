<?php
declare(strict_types=1);
/**
 * Страница 429: с этого адреса слишком часто открывают вход в админку (защита от засорения хранилища сессий).
 */
$this->layout('admin/layout', ['title' => 'Слишком часто', 'user' => null, 'csrf' => '']);
?>
<section class="card card-narrow" aria-labelledby="many-title">
  <h1 id="many-title" class="h1">Подождите немного</h1>
  <p class="alert alert-error" role="alert">С вашего адреса слишком часто открывали страницу входа. Попробуйте через 10 минут.</p>
</section>
