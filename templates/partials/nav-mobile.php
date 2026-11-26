<?php
declare(strict_types=1);

/**
 * Полноэкранное меню для экранов уже 1200 px. Открывается бургером из шапки (frontend/js/nav.js).
 * «Сведения…» раскрываются аккордеоном: 6 разделов и 5 подразделов, ниже — «Все сведения».
 * @var list<array<string,mixed>> $nav
 * @var array<string,mixed> $settings
 * @var string $currentPath
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\safe_url;
use function Groza\View\t;
use function Groza\View\tel;

$current = static fn (string $href): string => $href === $currentPath ? ' aria-current="page"' : '';
$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
?>
<div class="on-dark fixed inset-0 z-[60] flex flex-col overflow-y-auto overscroll-contain bg-forest text-cream desk:hidden" id="mobile-menu" role="dialog" aria-modal="true" aria-label="Меню" data-menu-panel hidden>
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-x-0 bottom-0 h-72 w-full text-sand opacity-[0.07]']) ?>
  <div class="wrap relative flex h-16 shrink-0 items-center gap-4 sm:gap-6">
    <?= $this->partial('partials/brand') ?>
    <button class="grid size-11 place-items-center rounded-btn border border-cream/35 text-cream transition-colors hover:border-cream hover:bg-cream/10" type="button" aria-label="Закрыть меню" data-menu-close>
      <?= icon('x', 'size-6') ?>
    </button>
    <a class="nojs-menu grid size-11 place-items-center rounded-btn border border-cream/35 text-cream no-underline transition-colors hover:border-cream hover:bg-cream/10" href="#main" aria-label="Закрыть меню">
      <?= icon('x', 'size-6') ?>
    </a>
  </div>

  <nav class="wrap relative flex flex-1 flex-col pb-10 pt-5" aria-label="Основное меню">
    <ul role="list">
      <?php foreach ($nav as $item): ?>
        <?php if ($item['kind'] === 'button'): ?>
          <li class="mb-4">
            <?= $this->partial('components/button', [
                'label' => $item['label'],
                'href' => $item['href'],
                'size' => 'lg',
                'class' => 'w-full',
                'attrs' => $item['href'] === $currentPath ? ['aria-current' => 'page'] : [],
            ]) ?>
          </li>
        <?php elseif ($item['kind'] === 'link'): ?>
          <li class="border-b border-cream/15">
            <a class="flex items-center justify-between gap-4 py-4 font-display text-xl font-medium uppercase tracking-nav text-cream no-underline aria-[current=page]:text-gold" href="<?= e($item['href']) ?>"<?= $current($item['href']) ?>>
              <?= t($item['label']) ?>
              <?= icon('chevron-right', 'size-5 text-gold') ?>
            </a>
          </li>
        <?php else: ?>
          <?php $listId = 'm-' . trim(str_replace('/', '-', $item['href']), '-'); ?>
          <li class="border-b border-cream/15">
            <button class="group flex w-full items-center justify-between gap-4 py-4 text-left font-display text-lg font-medium uppercase leading-snug tracking-nav text-cream" type="button" aria-expanded="false" aria-controls="<?= e($listId) ?>" data-accordion>
              <?= t($item['label']) ?>
              <?= icon('chevron-down', 'size-5 text-gold transition-transform duration-200 group-aria-expanded:rotate-180') ?>
            </button>
            <div class="pb-5" id="<?= e($listId) ?>" hidden>
              <ul role="list" data-menu-list>
                <?php foreach ($item['children'] as $section): ?>
                  <li>
                    <a class="block py-2.5 font-semibold leading-snug text-cream no-underline aria-[current=page]:text-gold" href="<?= e($section['href']) ?>"<?= $current($section['href']) ?>><?= t($section['label']) ?></a>
                    <?php if (!empty($section['children'])): ?>
                      <ul class="mb-2 ml-1.5 border-l border-dashed border-sage/50 pl-4" role="list">
                        <?php foreach ($section['children'] as $sub): ?>
                          <li><a class="block py-2 leading-snug text-sage no-underline hover:text-cream" href="<?= e($sub['href']) ?>"><?= t($sub['label']) ?></a></li>
                        <?php endforeach ?>
                      </ul>
                    <?php endif ?>
                  </li>
                <?php endforeach ?>
              </ul>
              <a class="mt-3 inline-flex items-center gap-2 font-display text-sm font-semibold uppercase tracking-[0.12em] text-gold no-underline" href="<?= e($item['href']) ?>"<?= $current($item['href']) ?>>
                Все сведения <?= icon('arrow-right', 'size-4') ?>
              </a>
            </div>
          </li>
        <?php endif ?>
      <?php endforeach ?>
    </ul>

    <div class="mt-auto pt-10">
      <?php if (!empty($settings['phone'])): ?>
        <a class="tabular whitespace-nowrap font-display text-[1.75rem] font-semibold leading-none tracking-[0.02em] text-cream no-underline" href="<?= e(tel($settings['phone'])) ?>"><?= t(Phone::pretty($settings['phone'])) ?></a>
      <?php endif ?>
      <?php if ($hours): ?>
        <p class="mt-2 text-[0.9375rem] text-sage"><?= t('Офис в Перми: ' . implode(', ', $hours)) ?></p>
      <?php endif ?>
      <div class="mt-5 flex flex-wrap gap-3">
        <?php if (!empty($settings['whatsapp'])): ?>
          <?= $this->partial('components/button', [
              'label' => 'WhatsApp',
              'href' => 'https://wa.me/' . ltrim((string) $settings['whatsapp'], '+'),
              'variant' => 'outline-light',
              'icon' => 'message-circle',
              'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
          ]) ?>
        <?php endif ?>
        <?php if (safe_url($settings['vk'] ?? '') !== ''): ?>
          <?= $this->partial('components/button', [
              'label' => 'ВКонтакте',
              'href' => safe_url($settings['vk']),
              'variant' => 'outline-light',
              'icon' => 'vk',
              'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
          ]) ?>
        <?php endif ?>
      </div>
      <a class="mt-7 inline-flex items-center gap-2 py-1 font-semibold text-sage no-underline hover:text-cream" href="/admin/" rel="nofollow">
        <?= icon('log-in', 'size-4 text-gold') ?><?= t('Вход для сотрудников') ?>
      </a>
    </div>
  </nav>
</div>
