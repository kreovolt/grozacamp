<?php
/**
 * @var string $login
 * @var string $next
 * @var string|null $error
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Вход', 'user' => null, 'csrf' => $csrf]);
?>
<section class="card card-narrow" aria-labelledby="login-title">
  <h1 id="login-title" class="h1">Вход в админку</h1>
<?php if ($error !== null): ?>
  <p class="alert alert-error" role="alert"><?= e($error) ?></p>
<?php endif ?>
  <form method="post" action="/admin/login/" class="form" autocomplete="on">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label for="login">Логин</label>
      <input id="login" name="login" type="text" value="<?= e($login) ?>" required maxlength="64" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false">
    </div>
    <div class="field">
      <label for="password">Пароль</label>
      <input id="password" name="password" type="password" required maxlength="512" autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary">Войти</button>
  </form>
</section>
