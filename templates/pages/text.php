<?php
declare(strict_types=1);

/**
 * Страница-документ: заголовок, оглавление (если разделов два и больше) и текст из админки; пустой текст — «Информация обновляется».
 * @var string $title
 * @var string $description
 * @var string $html            richtext с id у подзаголовков
 * @var list<array{id:string,label:string}> $toc
 * @var list<array{title:string,url:string,bytes:int}> $files   PDF-версия, если загружена
 */
use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\pending;
use function Groza\View\t;
use function Groza\View\typeset;

$this->layout('layout', ['page' => 'text', 'title' => $title, 'description' => $description]);

$size = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '';
    }

    return \Groza\Pages\SvedeniyaPage::fileSize($bytes);
};
$withToc = count($toc) >= 2;
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="text-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap pb-14 pt-8 desk:pb-20 desk:pt-12">
    <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => $title]], 'tone' => 'dark', 'class' => 'mb-8']) ?>
    <h1 class="max-w-[24ch] font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="text-title"><?= t($title) ?></h1>
  </div>
</section>

<div class="wrap grid grid-cols-1 gap-8 py-12 sm:py-16 <?= $withToc ? 'lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] lg:gap-16' : '' ?> desk:py-20" data-block="text">
  <?php if ($withToc): ?>
    <?= $this->partial('components/toc', ['items' => $toc]) ?>
  <?php endif ?>
  <div class="min-w-0 max-w-[48rem]">
    <?php if ($html === ''): ?>
      <p class="text-lg leading-relaxed"><?= pending('') ?></p>
    <?php else: ?>
      <div class="richtext document leading-relaxed text-ink-soft"><?= typeset($html) ?></div>
    <?php endif ?>
    <?php if ($files !== []): ?>
      <ul class="mt-10 border-t border-dashed border-moss/45" role="list">
        <?php foreach ($files as $f): ?>
          <li class="border-b border-dashed border-moss/45">
            <a class="flex items-center gap-3 py-4 font-semibold text-rust-deep no-underline hover:text-rust-dark" href="<?= e($f['url']) ?>" target="_blank" rel="noopener">
              <?= icon('arrow-right', 'size-5 shrink-0') ?>
              <span><?= t($f['title'] !== '' ? $f['title'] : 'Скачать PDF') ?> <span class="font-normal text-ink-soft">(PDF<?= $size($f['bytes']) !== '' ? ', ' . $size($f['bytes']) : '' ?>)</span></span>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </div>
</div>
