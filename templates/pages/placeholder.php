<?php
declare(strict_types=1);

/**
 * ВРЕМЕННАЯ страница-заглушка (Task 4): раздел ещё не сделан, а пункт меню уже есть.
 * Её заменяют страницы задач 7, 9, 10, 11.
 * @var string $title
 * @var list<array{label:string, href?:string}> $crumbs
 * @var array<string,mixed> $settings
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\tel;

$this->layout('layout', [
    'title' => $crumbs === [] ? 'Детский лагерь «Гроза» — лагерь приключений под Пермью' : $title . ' — детский лагерь «Гроза», Пермь',
]);
?>
<section class="on-dark relative overflow-hidden bg-forest text-cream">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap relative pb-14 pt-8 desk:pb-20 desk:pt-12">
    <?= $this->partial('partials/breadcrumbs', ['items' => $crumbs, 'tone' => 'dark', 'class' => 'mb-8']) ?>
    <h1 class="max-w-[24ch] font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.02] tracking-[0.01em]"><?= e($title) ?></h1>
  </div>
</section>
<div class="wrap py-12 desk:py-16">
  <p class="max-w-[60ch] text-lg leading-relaxed text-ink-soft">
    Раздел наполняется.
    <?php if (!empty($settings['phone'])): ?>
      Всё расскажем по телефону
      <a class="tabular whitespace-nowrap font-semibold text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="<?= e(tel($settings['phone'])) ?>"><?= e(Phone::pretty($settings['phone'])) ?></a>.
    <?php endif ?>
  </p>
</div>
