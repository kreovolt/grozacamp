<?php
declare(strict_types=1);

/**
 * Страница после отправки формы без JavaScript.
 * @var string $form booking | vacancy
 */
use function Groza\View\e;

$isVacancy = ($form ?? 'booking') === 'vacancy';
$this->layout('layout', ['title' => 'Спасибо — детский лагерь «Гроза», Пермь', 'noindex' => true]);
?>
<div class="wrap py-16 desk:py-24">
  <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.02]"><?= e($isVacancy ? 'Спасибо! Мы свяжемся с вами' : 'Спасибо! Мы перезвоним') ?></h1>
  <p class="mt-5 max-w-[56ch] text-lg leading-relaxed text-ink-soft">
    <?= e($isVacancy ? 'Анкета получена, ответим в ближайшие дни.' : 'Заявка получена. Позвоним в рабочее время — Пн–Пт с 10:00 до 19:00.') ?>
  </p>
  <p class="mt-8"><a class="font-display text-base font-semibold uppercase tracking-btn text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="/">На главную</a></p>
</div>
