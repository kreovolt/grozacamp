<?php
declare(strict_types=1);

/**
 * Шапка: шеврон и название, меню (десктоп) или «Позвонить» + бургер (уже 1200 px).
 * Липкая на любой ширине; верхняя полоса (partials/topbar) уезжает вместе со страницей. Высота шапки — в scroll-padding-top (app.css).
 * @var array<string,mixed> $settings
 */
use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\tel;
?>
<header class="on-dark sticky top-0 z-40 bg-forest text-cream transition-shadow duration-200 data-[scrolled]:shadow-[0_10px_24px_-18px_rgb(12_22_16/0.7)]" data-header>
  <div class="wrap relative flex h-16 items-center gap-4 sm:gap-6 desk:h-20">
    <?= $this->partial('partials/brand') ?>
    <?= $this->partial('partials/nav-desktop') ?>
    <div class="flex items-center gap-2 desk:hidden">
      <?php if (!empty($settings['phone'])): ?>
        <a class="grid size-11 place-items-center rounded-btn bg-rust-dark text-white transition-colors hover:bg-rust-deep" href="<?= e(tel($settings['phone'])) ?>" aria-label="Позвонить">
          <?= icon('phone', 'size-5') ?>
        </a>
      <?php endif ?>
      <?php /* Без JS бургер — ссылка-якорь на панель (:target в app.css); с JS её скрывает html.js */ ?>
      <a class="nojs-menu grid size-11 place-items-center rounded-btn border border-cream/35 text-cream no-underline transition-colors hover:border-cream hover:bg-cream/10" href="#mobile-menu" aria-label="Меню">
        <?= icon('menu', 'size-6') ?>
      </a>
      <button class="grid size-11 place-items-center rounded-btn border border-cream/35 text-cream transition-colors hover:border-cream hover:bg-cream/10" type="button" aria-label="Меню" aria-expanded="false" aria-controls="mobile-menu" data-menu-open>
        <?= icon('menu', 'size-6') ?>
      </button>
    </div>
  </div>
</header>
<?= $this->partial('partials/nav-mobile') ?>
