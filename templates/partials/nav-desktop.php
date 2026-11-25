<?php
declare(strict_types=1);

/**
 * Меню в шапке (от 1200 px). Пункты и порядок — config/nav.php (спека §5.2).
 * «Сведения…» — кнопка, раскрывающая список; сам раздел /svedeniya/ — ссылка «Все сведения» под списком.
 * @var list<array<string,mixed>> $nav
 * @var string $currentPath
 */
use Groza\Content\Nav;

use function Groza\View\e;
use function Groza\View\icon;

$current = static fn (string $href): string => $href === $currentPath ? ' aria-current="page"' : '';
?>
<nav class="hidden desk:block" aria-label="Основное меню">
  <ul class="flex items-center gap-6 xl:gap-7" role="list">
    <?php foreach ($nav as $item): ?>
      <?php if ($item['kind'] === 'button'): ?>
        <li>
          <?= $this->partial('components/button', [
              'label' => $item['label'],
              'href' => $item['href'],
              'size' => 'sm',
              'class' => 'whitespace-nowrap',
              'attrs' => $item['href'] === $currentPath ? ['aria-current' => 'page'] : [],
          ]) ?>
        </li>
      <?php elseif ($item['kind'] === 'link'): ?>
        <li>
          <a class="nav-link whitespace-nowrap text-[0.9375rem]" href="<?= e($item['href']) ?>"<?= $current($item['href']) ?>><?= e($item['label']) ?></a>
        </li>
      <?php else: ?>
        <?php $panelId = 'dd-' . trim(str_replace('/', '-', $item['href']), '-'); ?>
        <li data-dropdown>
          <button class="nav-link group block max-w-[14.5rem] text-left text-[0.8125rem] leading-[1.3]" type="button" aria-expanded="false" aria-controls="<?= e($panelId) ?>" data-dropdown-toggle<?= Nav::isActive($item['href'], $currentPath) ? ' data-active' : '' ?>>
            <?= e($item['label']) ?>&nbsp;<?= icon('chevron-down', 'size-4 align-[-0.25em] text-gold transition-transform duration-200 group-aria-expanded:rotate-180') ?>
          </button>
          <div class="absolute right-[var(--gutter)] top-full z-50 w-[31rem] rounded-b-card bg-paper text-ink shadow-pop" id="<?= e($panelId) ?>" data-dropdown-panel hidden>
            <ul class="px-3 pb-2 pt-3" role="list">
              <?php foreach ($item['children'] as $section): ?>
                <li>
                  <a class="block rounded-lg px-3 py-2 font-semibold leading-snug text-ink no-underline transition-colors hover:bg-sand hover:text-rust-deep aria-[current=page]:text-rust-deep" href="<?= e($section['href']) ?>"<?= $current($section['href']) ?>><?= e($section['label']) ?></a>
                  <?php if (!empty($section['children'])): ?>
                    <ul class="mb-1.5 ml-[1.125rem] border-l border-dashed border-moss/60 pl-2" role="list">
                      <?php foreach ($section['children'] as $sub): ?>
                        <li>
                          <a class="block rounded-lg px-3 py-1.5 text-[0.9375rem] leading-snug text-ink-soft no-underline transition-colors hover:bg-sand hover:text-rust-deep" href="<?= e($sub['href']) ?>"><?= e($sub['label']) ?></a>
                        </li>
                      <?php endforeach ?>
                    </ul>
                  <?php endif ?>
                </li>
              <?php endforeach ?>
            </ul>
            <a class="group/all flex items-center justify-between gap-3 rounded-b-card border-t border-sand-deep bg-sand/60 px-6 py-3.5 font-display text-[0.8125rem] font-semibold uppercase tracking-[0.12em] text-rust-deep no-underline transition-colors hover:bg-sand" href="<?= e($item['href']) ?>"<?= $current($item['href']) ?>>
              Все сведения
              <?= icon('arrow-right', 'size-4 transition-transform group-hover/all:translate-x-0.5') ?>
            </a>
          </div>
        </li>
      <?php endif ?>
    <?php endforeach ?>
  </ul>
</nav>
