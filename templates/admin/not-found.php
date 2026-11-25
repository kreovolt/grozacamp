<?php
/**
 * @var string|null $heading
 * @var string|null $message
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => $heading ?? 'Не найдено', 'user' => $user, 'csrf' => $csrf]);
?>
<section class="card" aria-labelledby="nf-title">
  <h1 id="nf-title" class="h1"><?= e($heading ?? 'Такой страницы нет') ?></h1>
  <p><?= e($message ?? 'Проверьте адрес или вернитесь к разделам.') ?></p>
  <p class="back"><a href="/admin/">← К разделам</a></p>
</section>
