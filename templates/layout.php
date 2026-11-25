<?php
/**
 * Общий макет страниц сайта.
 * @var string $content  разметка страницы
 * @var string|null $title  полный <title>
 * @var string|null $description
 * @var bool|null $noindex
 */
use function Groza\View\asset;
use function Groza\View\e;

$title ??= 'Детский лагерь «Гроза» — лагерь приключений под Пермью';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if (!empty($description)): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif ?>
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif ?>
<meta name="theme-color" content="#22392c">
<link rel="preload" href="/assets/fonts/manrope-cyrillic-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/oswald-cyrillic-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<script src="<?= e(asset('/assets/js/main.js')) ?>" defer></script>
</head>
<body class="flex min-h-screen flex-col">
<a class="skip-link" href="#main">Перейти к содержанию</a>
<?= $this->partial('partials/topbar') ?>
<?= $this->partial('partials/header') ?>
<main id="main" tabindex="-1" class="flex-1">
<?= $content ?>
</main>
<?= $this->partial('partials/footer') ?>
</body>
</html>
