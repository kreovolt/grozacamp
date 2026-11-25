<?php
declare(strict_types=1);

/**
 * Оглавление страницы: ссылки на якоря разделов. На телефоне раскрывающийся список (открыт по умолчанию, работает без JS),
 * на широком экране — всегда открытый столбец; закрепляется при прокрутке (sticky) — родителю не надо возвращаться наверх.
 * @var list<array{id:string,label:string}> $items
 * @var string|null $title   подпись, «Содержание» по умолчанию
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\icon;

if (($items ?? []) === []) {
    return;
}
?>
<nav data-toc class="<?= e(trim('self-start lg:sticky lg:top-6 ' . ($class ?? ''))) ?>" aria-label="<?= e($title ?? 'Содержание') ?>">
  <details class="group rounded-card bg-paper p-5 shadow-card lg:bg-transparent lg:p-0 lg:shadow-none" open>
    <summary class="flex cursor-pointer list-none items-center justify-between font-display text-[1.125rem] font-semibold uppercase tracking-[0.04em] text-ink lg:pointer-events-none lg:cursor-default [&::-webkit-details-marker]:hidden">
      <span><?= e($title ?? 'Содержание') ?></span>
      <?= icon('chevron-down', 'size-5 text-rust-dark transition-transform duration-200 group-open:rotate-180 lg:hidden') ?>
    </summary>
    <ol class="mt-3 border-t border-dashed border-moss/45 lg:mt-4" role="list">
      <?php foreach ($items as $item): ?>
        <li class="border-b border-dashed border-moss/45">
          <a class="block py-2.5 leading-snug text-ink-soft no-underline transition-colors hover:text-rust-deep" href="#<?= e($item['id']) ?>"><?= e($item['label']) ?></a>
        </li>
      <?php endforeach ?>
    </ol>
  </details>
</nav>
