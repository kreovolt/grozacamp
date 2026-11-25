<?php
declare(strict_types=1);

/**
 * «Информация для родителей» (спека §7.4): герой, оглавление слева (закреплено) и разделы справа.
 * Раздел без текста показывает «Информация обновляется».
 * @var list<array<string,mixed>> $sections
 * @var list<array{id:string,label:string}> $toc
 * @var array<string,mixed> $settings
 */
use function Groza\View\e;
use function Groza\View\richtext;
use function Groza\View\icon;
use function Groza\View\pending;
use function Groza\View\photo;
use function Groza\View\picture;

$this->layout('layout', ['page' => 'info']);

$shot = static function (string $key, string $sizes): string {
    $img = photo($key);

    return picture($img, (string) ($img['alt'] ?? ''), $sizes, false, 'h-full w-full object-cover');
};
$h2 = 'font-display text-[clamp(1.5rem,1.2rem+1.1vw,2.125rem)] font-bold uppercase leading-[1.12] tracking-[0.015em] text-ink';
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="info-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap grid gap-10 pb-14 pt-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-center lg:gap-16 desk:pb-20 desk:pt-12">
    <div>
      <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => 'Информация для родителей']], 'tone' => 'dark', 'class' => 'mb-8']) ?>
      <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="info-title">Информация для родителей</h1>
      <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg">Что собрать, какие документы взять, как передать лекарства и как быть на связи с ребёнком. Не нашли ответ, позвоните в офис.</p>
    </div>
    <div class="hidden items-start lg:flex">
      <?= $this->partial('components/polaroid', [
          'media' => $shot('indoor-circle', '(min-width: 1024px) 22rem, 60vw'),
          'ratio' => '4/3',
          'tilt' => 'left',
          'caption' => 'Растяжка вместе с вожатой',
          'class' => 'relative z-10 w-[58%] shrink-0',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('audience-smiles', '(min-width: 1024px) 16rem, 40vw'),
          'ratio' => '4/3',
          'tilt' => 'right',
          'caption' => 'Смотрим выступление',
          'class' => 'relative z-20 -ml-[10%] mt-14 w-[48%] shrink-0',
      ]) ?>
    </div>
  </div>
</section>

<div class="wrap grid gap-8 py-12 sm:py-16 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] lg:gap-16 desk:py-20" data-block="content">
  <?= $this->partial('components/toc', ['items' => $toc]) ?>

  <div class="min-w-0 max-w-[48rem]">
    <?php foreach ($sections as $i => $s): ?>
      <section data-reveal class="scroll-mt-6 border-t border-dashed border-moss/45 py-9 first:border-t-0 first:pt-0 last:pb-0" id="<?= e($s['id']) ?>" aria-labelledby="<?= e($s['id']) ?>-title">
        <h2 class="<?= $h2 ?>" id="<?= e($s['id']) ?>-title"><?= e($s['label']) ?></h2>
        <?php if (!empty($s['subtitle'])): ?>
          <p class="mt-2 text-ink-soft"><?= e($s['subtitle']) ?></p>
        <?php endif ?>

        <?php if ($s['kind'] === 'html' || $s['kind'] === 'rules'): ?>
          <?php if ($s['html'] === ''): ?>
            <p class="mt-5 leading-relaxed"><?= pending('') ?></p>
          <?php else: ?>
            <div class="richtext mt-5 leading-relaxed text-ink-soft"><?= richtext((string) $s['html']) ?></div>
          <?php endif ?>
          <?php if ($s['kind'] === 'rules' && $s['html'] !== ''): ?>
            <p class="mt-5"><a class="group/more inline-block font-display text-[0.9375rem] font-semibold uppercase leading-snug tracking-btn text-rust-deep no-underline hover:text-rust-dark" href="/pravila/">Все правила пребывания&nbsp;<?= icon('arrow-right', 'inline-block size-4 align-[-0.2em] transition-transform group-hover/more:translate-x-0.5') ?></a></p>
          <?php endif ?>

        <?php elseif ($s['kind'] === 'list'): ?>
          <?php if ($s['items'] === []): ?>
            <p class="mt-5 leading-relaxed"><?= pending('') ?></p>
          <?php else: ?>
            <ul class="mt-5 grid gap-x-10 border-t border-dashed border-moss/45 sm:grid-cols-2" role="list">
              <?php foreach ($s['items'] as $item): ?>
                <li class="flex gap-3.5 border-b border-dashed border-moss/45 py-3.5 leading-snug">
                  <?= icon('check', 'mt-0.5 size-5 shrink-0 text-moss') ?>
                  <span class="text-ink"><?= e($item) ?></span>
                </li>
              <?php endforeach ?>
            </ul>
          <?php endif ?>

        <?php elseif ($s['kind'] === 'faq'): ?>
          <?= $this->partial('components/faq-list', ['items' => $s['faq'], 'class' => 'mt-5']) ?>
        <?php endif ?>
      </section>
    <?php endforeach ?>
  </div>
</div>

<section data-block="cta">
  <?= $this->partial('components/cta-final', [
      'title' => 'Выберите смену',
      'lead' => 'Остались вопросы? Позвоните или напишите в WhatsApp, ответим в рабочее время.',
      'href' => '/booking/#smeny',
      'label' => 'Смотреть смены',
      'settings' => $settings,
  ]) ?>
</section>
