<?php
declare(strict_types=1);

/**
 * Хлебные крошки внутренних страниц. Последний пункт — текущая страница (без ссылки).
 * @var list<array{label:string, href?:string}> $items
 * @var string|null $tone   light | dark
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\t;

if (empty($items)) {
    return;
}
$dark = ($tone ?? 'light') === 'dark';
$last = count($items) - 1;
?>
<nav class="<?= e(trim('text-[0.875rem] leading-snug ' . ($dark ? 'text-sage ' : 'text-ink-soft ') . ($class ?? ''))) ?>" aria-label="Хлебные крошки">
  <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1" role="list">
    <?php foreach ($items as $i => $item): ?>
      <li class="flex items-center gap-1.5">
        <?php if ($i > 0): ?>
          <?= icon('chevron-right', 'size-3.5 ' . ($dark ? 'text-gold' : 'text-moss')) ?>
        <?php endif ?>
        <?php if ($i < $last && !empty($item['href'])): ?>
          <a class="tap underline underline-offset-4 transition-colors <?= $dark ? 'decoration-sage/40 hover:text-cream hover:decoration-cream' : 'decoration-ink-soft/35 hover:text-ink hover:decoration-ink' ?>" href="<?= e($item['href']) ?>"><?= t($item['label']) ?></a>
        <?php else: ?>
          <span class="font-semibold <?= $dark ? 'text-cream' : 'text-ink' ?>" aria-current="page"><?= t($item['label']) ?></span>
        <?php endif ?>
      </li>
    <?php endforeach ?>
  </ol>
</nav>
