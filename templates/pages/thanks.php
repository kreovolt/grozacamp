<?php
declare(strict_types=1);

/**
 * Страница после отправки формы без JavaScript.
 * @var string $form booking | vacancy
 * @var array<string,mixed> $settings
 */
use function Groza\View\e;
use function Groza\View\t;

$isVacancy = ($form ?? 'booking') === 'vacancy';
$hours = implode(', ', array_filter([trim((string) ($settings['hoursWeekdays'] ?? '')), trim((string) ($settings['hoursWeekend'] ?? ''))]));
$this->layout('layout', ['page' => 'thanks', 'noindex' => true]);
?>
<?php /* Как у остальных внутренних страниц: лесная полоса с заголовком, ниже — песочный блок (не сливается с подвалом) */ ?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="thanks-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap pb-14 pt-12 desk:pb-20 desk:pt-16">
    <h1 class="max-w-[24ch] font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="thanks-title"><?= t($isVacancy ? 'Спасибо! Мы свяжемся с вами' : 'Спасибо! Мы перезвоним') ?></h1>
    <p class="mt-5 max-w-[46ch] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg">
      <?= t($isVacancy ? 'Анкета получена, ответим в ближайшие дни.' : 'Заявка получена. Позвоним в рабочее время' . ($hours !== '' ? ' — ' . $hours : '') . '.') ?>
    </p>
  </div>
</section>

<div class="wrap py-12 sm:py-16">
  <p><a class="tap inline-block font-display text-base font-semibold uppercase tracking-btn text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="/"><?= t('На главную') ?></a></p>
</div>
