<?php
declare(strict_types=1);
/**
 * @var string|null $error
 * @var bool $done
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Смена пароля', 'user' => $user, 'csrf' => $csrf]);
?>
<section class="card card-narrow" aria-labelledby="pw-title">
  <h1 id="pw-title" class="h1">Смена пароля</h1>
<?php if ($done): ?>
  <p class="alert alert-ok" role="status">Пароль изменён.</p>
<?php endif ?>
<?php if ($error !== null): ?>
  <p class="alert alert-error" role="alert"><?= e($error) ?></p>
<?php endif ?>
  <form method="post" action="/admin/password/" class="form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field">
      <label for="current">Текущий пароль</label>
      <input id="current" name="current" type="password" required maxlength="512" autocomplete="current-password">
    </div>
    <div class="field">
      <label for="new">Новый пароль</label>
      <input id="new" name="new" type="password" required minlength="10" maxlength="512" autocomplete="new-password" aria-describedby="new-hint">
      <p id="new-hint" class="hint">Не короче 10 символов.</p>
    </div>
    <div class="field">
      <label for="repeat">Новый пароль ещё раз</label>
      <input id="repeat" name="repeat" type="password" required minlength="10" maxlength="512" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary">Сменить пароль</button>
  </form>
  <p class="back"><a href="/admin/">← В админку</a></p>
</section>
