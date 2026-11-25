<?php
declare(strict_types=1);

/**
 * «Полароид»: фото в белой рамке 8 px, с лёгким поворотом и мягкой тёплой тенью; при наведении чуть приподнимается.
 * @var string $media        готовая разметка фото (picture() из Task 5 или <img>), должна заполнять рамку: class="h-full w-full object-cover"
 * @var string|null $caption подпись под фото
 * @var string|null $tilt    left (−2°) | right (+1,5°) | slight (−1°) | none
 * @var string|null $ratio   4/5 (по умолчанию) | 1/1 | 4/3 | 3/4
 * @var string|null $class
 */
use function Groza\View\e;

$tilts = [
    'left' => '-rotate-2',
    'right' => 'rotate-[1.5deg]',
    'slight' => '-rotate-1',
    'none' => '',
];
$ratios = [
    '4/5' => 'aspect-[4/5]',
    '1/1' => 'aspect-square',
    '4/3' => 'aspect-[4/3]',
    '3/4' => 'aspect-[3/4]',
];
$classes = implode(' ', array_filter([
    'polaroid m-0 bg-paper p-2 shadow-polaroid',
    'transition-[transform,box-shadow] duration-300 ease-out motion-safe:hover:-translate-y-1.5 hover:shadow-lift',
    $tilts[$tilt ?? 'left'] ?? $tilts['left'],
    $class ?? '',
]));
?>
<figure class="<?= e($classes) ?>">
  <div class="<?= $ratios[$ratio ?? '4/5'] ?? $ratios['4/5'] ?> overflow-hidden bg-sand-deep">
    <?= $media ?>
  </div>
  <?php if (!empty($caption)): ?>
    <figcaption class="px-1 pb-1 pt-2.5 text-[0.9375rem] leading-snug text-ink-soft"><?= e($caption) ?></figcaption>
  <?php endif ?>
</figure>
