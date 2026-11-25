<?php
declare(strict_types=1);

/**
 * Общая рамка страниц раздела сведений: заголовок на тёмной полосе, дата обновления, боковое оглавление и содержимое.
 * @var string $content
 * @var string $title
 * @var string $description
 * @var list<array{label:string,href?:string}> $crumbs
 * @var list<array{id:string,label:string}> $toc
 * @var string|null $updated    дата YYYY-MM-DD
 * @var string $updatedText     «20 ноября 2026»
 */
use function Groza\View\e;

$this->layout('layout', ['page' => 'svedeniya', 'title' => $title, 'description' => $description]);
$withToc = ($toc ?? []) !== [];
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="sved-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap pb-12 pt-8 desk:pb-16 desk:pt-12">
    <?= $this->partial('partials/breadcrumbs', ['items' => $crumbs, 'tone' => 'dark', 'class' => 'mb-8']) ?>
    <h1 class="max-w-[52rem] font-display text-[clamp(1.875rem,1.3rem+2.4vw,3.25rem)] font-bold uppercase leading-[1.08] tracking-[0.01em] text-[#f4efe3]" id="sved-title"><?= e($title) ?></h1>
    <?php if (($updated ?? null) !== null): ?>
      <p class="mt-5 text-[0.9375rem] text-sage">Обновлено: <time datetime="<?= e($updated) ?>"><?= e($updatedText) ?></time></p>
    <?php endif ?>
  </div>
</section>

<div class="wrap grid gap-8 py-12 sm:py-16 <?= $withToc ? 'lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] lg:gap-16' : '' ?> desk:py-20" data-block="sved">
  <?php if ($withToc): ?>
    <?= $this->partial('components/toc', ['items' => $toc]) ?>
  <?php endif ?>
  <div class="min-w-0 max-w-[60rem]">
    <?= $content ?>
  </div>
</div>
