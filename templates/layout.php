<?php
declare(strict_types=1);
/**
 * Общий макет страниц сайта.
 * @var string $content  разметка страницы
 * @var string|null $page  идентификатор страницы для Meta (home, booking, shift, text, …)
 * @var string|null $title  название страницы без суффикса (по умолчанию — из config/seo.php)
 * @var string|null $description
 * @var bool|null $noindex
 * @var list<array<string,mixed>>|null $jsonLd  структурированные данные страницы
 */
use Groza\Seo\JsonLd;
use Groza\Seo\Meta;

use function Groza\View\asset;
use function Groza\View\e;

$page ??= 'page';
$meta = Meta::for($page, ['baseUrl' => $baseUrl, 'path' => $currentPath, 'title' => $title ?? null, 'description' => $description ?? null]);
$hidden = !empty($noindex) || $env === 'staging';
$jsonLd = $jsonLd ?? [];
if ($page === 'home') {
    array_unshift($jsonLd, JsonLd::camp($settings, $baseUrl));
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['description']) ?>">
<?php if ($hidden): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php endif ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Детский лагерь «Гроза»">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="<?= e($meta['title']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta property="og:image" content="<?= e($meta['image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#22392c">
<link rel="preload" href="/assets/fonts/manrope-cyrillic-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/oswald-cyrillic-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<script src="<?= e(asset('/assets/js/main.js')) ?>" defer></script>
<?php if (str_contains($content, 'data-reveal')): /* GSAP — только на страницах с анимацией появления */ ?>
<script src="<?= e(asset('/assets/js/motion.js')) ?>" defer></script>
<?php endif ?>
<?php if ($metrikaId !== ''): ?>
<script src="<?= e(asset('/assets/js/metrika.js')) ?>" data-id="<?= e($metrikaId) ?>" defer></script>
<?php endif ?>
<?php foreach ($jsonLd as $ld): ?>
<script type="application/ld+json"><?= JsonLd::encode($ld) ?></script>
<?php endforeach ?>
</head>
<body class="flex min-h-screen flex-col" id="top">
<a class="skip-link" href="#main">Перейти к содержанию</a>
<?= $this->partial('partials/topbar') ?>
<?= $this->partial('partials/header') ?>
<main id="main" tabindex="-1" class="flex-1">
<?= $content ?>
</main>
<?= $this->partial('partials/footer') ?>
<?= $this->partial('partials/to-top') ?>
<?php if ($metrikaId !== ''): ?>
<?= $this->partial('partials/cookie-notice') ?>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= e($metrikaId) ?>" width="1" height="1" alt="" class="absolute -left-[9999px]"></div></noscript>
<?php endif ?>
</body>
</html>
