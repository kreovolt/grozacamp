<?php
declare(strict_types=1);

/**
 * Страница после отправки формы без JavaScript.
 * @var string $form booking | vacancy
 * @var array<string,mixed> $settings
 */
use function Groza\View\e;

$isVacancy = ($form ?? 'booking') === 'vacancy';
$hours = implode(', ', array_filter([trim((string) ($settings['hoursWeekdays'] ?? '')), trim((string) ($settings['hoursWeekend'] ?? ''))]));
$this->layout('layout', ['page' => 'thanks', 'noindex' => true]);
?>
<div class="wrap py-16 desk:py-24">
  <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.02]"><?= e($isVacancy ? 'Спасибо! Мы свяжемся с вами' : 'Спасибо! Мы перезвоним') ?></h1>
  <p class="mt-5 max-w-[56ch] text-lg leading-relaxed text-ink-soft">
    <?= e($isVacancy ? 'Анкета получена, ответим в ближайшие дни.' : 'Заявка получена. Позвоним в рабочее время' . ($hours !== '' ? ' — ' . $hours : '') . '.') ?>
  </p>
  <p class="mt-8"><a class="font-display text-base font-semibold uppercase tracking-btn text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="/">На главную</a></p>
</div>
