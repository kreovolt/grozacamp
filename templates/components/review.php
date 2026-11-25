<?php
declare(strict_types=1);

/**
 * Отзыв родителя: цитата и подпись. Крупный вариант — для первого отзыва в блоке.
 * @var array{text:string, author?:string} $review
 * @var bool|null $featured
 * @var string|null $class
 */
use function Groza\View\e;

$featured = !empty($featured);
$author = trim((string) ($review['author'] ?? ''));
?>
<figure class="<?= e(trim('review relative ' . ($featured ? 'rounded-card bg-paper p-7 shadow-card sm:p-10 ' : 'py-6 ') . ($class ?? ''))) ?>">
  <?php /* кавычка — оформление; у крупного отзыва стоит над текстом, у обычного — на поле слева */ ?>
  <span class="pointer-events-none block select-none font-display font-bold text-rust/30 <?= $featured ? 'h-9 text-[4.5rem] leading-[0.55] sm:h-11 sm:text-[5.5rem]' : 'absolute left-0 top-[1.1rem] text-[2.75rem] leading-none' ?>" aria-hidden="true">«</span>
  <blockquote class="relative <?= $featured ? 'mt-2 text-[1.1875rem] leading-relaxed text-ink sm:text-[1.375rem] sm:leading-[1.55]' : 'pl-9 leading-relaxed text-ink' ?>">
    <p><?= e((string) $review['text']) ?></p>
  </blockquote>
  <?php if ($author !== ''): ?>
    <figcaption class="relative mt-4 font-semibold text-ink-soft <?= $featured ? 'sm:mt-6' : 'pl-9 text-[0.9375rem]' ?>"><?= e($author) ?></figcaption>
  <?php endif ?>
</figure>
