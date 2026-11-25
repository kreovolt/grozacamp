<?php
declare(strict_types=1);

/**
 * Заголовок секции: кикер (необязательный) + заголовок Oswald в верхнем регистре + вводный абзац.
 * Кикер золотой только на тёмном фоне; на светлом — rust-deep (золото на песке даёт 1.8:1).
 * @var string $title
 * @var string|null $kicker
 * @var string|null $lead
 * @var string|null $tone    light (по умолчанию, песок/бумага) | dark (forest, фото)
 * @var int|null $level      уровень заголовка, 2 по умолчанию
 * @var string|null $align   left | center
 * @var string|null $id      id заголовка (для якорей и aria-labelledby)
 * @var string|null $class
 */
use function Groza\View\e;

$dark = ($tone ?? 'light') === 'dark';
$center = ($align ?? 'left') === 'center';
$tag = 'h' . max(1, min(4, (int) ($level ?? 2)));
?>
<div class="<?= e(trim(($center ? 'mx-auto text-center ' : '') . 'max-w-3xl ' . ($class ?? ''))) ?>">
  <?php if (!empty($kicker)): ?>
    <p class="kicker mb-4 <?= $dark ? 'text-gold' : 'text-rust-deep' ?>"><?= e($kicker) ?></p>
  <?php endif ?>
  <<?= $tag ?> class="font-display text-[clamp(2rem,1.35rem+2.6vw,3.25rem)] font-bold uppercase leading-[1.1] tracking-[0.01em] <?= $dark ? 'text-cream' : 'text-ink' ?>"<?= !empty($id) ? ' id="' . e($id) . '"' : '' ?>><?= e($title) ?></<?= $tag ?>>
  <?php if (!empty($lead)): ?>
    <p class="mt-4 text-[1.0625rem] leading-relaxed sm:text-lg <?= $center ? 'mx-auto ' : '' ?>max-w-[60ch] <?= $dark ? 'text-cream/85' : 'text-ink-soft' ?>"><?= e($lead) ?></p>
  <?php endif ?>
</div>
