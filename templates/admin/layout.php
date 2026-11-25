<?php
declare(strict_types=1);
/**
 * Макет админки: без индексации, без чужих ресурсов, без встроенных скриптов и стилей (CSP).
 * @var string $content
 * @var string|null $title
 * @var array{login:string,name:string}|null $user
 * @var string|null $csrf
 * @var bool|null $editor страница с редактором текста (Trix): подключаются его стили и скрипт
 */
use function Groza\View\asset;
use function Groza\View\e;

$title = ($title ?? '') !== '' ? $title . ' — Админка «Гроза»' : 'Админка «Гроза»';
$user ??= null;
$editor ??= false;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<title><?= e($title) ?></title>
<meta name="theme-color" content="#22392c">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
<?php if ($editor): ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin-editor.css')) ?>">
<?php endif ?>
</head>
<body class="admin-body">
<a class="skip-link" href="#main">Перейти к содержанию</a>
<header class="admin-bar">
  <div class="admin-bar__in">
    <a class="admin-brand" href="/admin/">Гроза · админка</a>
<?php if ($user !== null): ?>
    <div class="admin-bar__user">
      <span class="admin-bar__name"><?= e($user['name']) ?></span>
      <a class="admin-link-light" href="/admin/password/">Пароль</a>
      <form method="post" action="/admin/logout/">
        <input type="hidden" name="_csrf" value="<?= e((string) $csrf) ?>">
        <button type="submit" class="btn btn-light">Выйти</button>
      </form>
    </div>
<?php endif ?>
  </div>
</header>
<main id="main" tabindex="-1" class="admin-main">
<?= $content ?>
</main>
<?php if ($editor): ?>
<script src="<?= e(asset('/assets/js/admin-editor.js')) ?>" defer></script>
<?php endif ?>
<script src="<?= e(asset('/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
