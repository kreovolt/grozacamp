<?php
/**
 * Временная стартовая страница (панель появится в следующих задачах).
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Админка', 'user' => $user, 'csrf' => $csrf]);
?>
<section class="card" aria-labelledby="home-title">
  <h1 id="home-title" class="h1">Админка</h1>
  <p>Здравствуйте, <?= e((string) ($user['name'] ?? '')) ?>.</p>
  <p><a href="/admin/password/">Сменить пароль</a></p>
</section>
