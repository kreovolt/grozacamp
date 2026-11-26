<?php
declare(strict_types=1);

/**
 * Тонкая полоса над шапкой (только десктоп): часы офиса, телефон, WhatsApp, ВКонтакте.
 * @var array<string,mixed> $settings
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\safe_url;
use function Groza\View\t;
use function Groza\View\tel;

$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
?>
<div class="on-dark hidden bg-forest-deep text-[0.875rem] text-sage desk:block" data-topbar>
  <div class="wrap flex h-10 items-center justify-between gap-6">
    <p class="flex items-center gap-2">
      <?php if ($hours): ?>
        <?= icon('clock', 'size-4 text-gold') ?>
        <span><?= t('Офис в Перми: ' . implode(', ', $hours)) ?></span>
      <?php endif ?>
    </p>
    <ul class="flex items-center gap-6" role="list">
      <?php if (!empty($settings['phone'])): ?>
        <li>
          <a class="link-quiet tabular flex items-center gap-2 whitespace-nowrap font-semibold text-cream" href="<?= e(tel((string) $settings['phone'])) ?>">
            <?= icon('phone', 'size-4 text-gold') ?><?= t(Phone::pretty((string) $settings['phone'])) ?>
          </a>
        </li>
      <?php endif ?>
      <?php if (!empty($settings['whatsapp'])): ?>
        <li>
          <a class="link-quiet flex items-center gap-2" href="https://wa.me/<?= e(ltrim((string) $settings['whatsapp'], '+')) ?>" rel="noopener" target="_blank">
            <?= icon('message-circle', 'size-4 text-gold') ?>WhatsApp
          </a>
        </li>
      <?php endif ?>
      <?php if (safe_url((string) ($settings['vk'] ?? '')) !== ''): ?>
        <li>
          <a class="link-quiet flex items-center gap-2" href="<?= e(safe_url((string) $settings['vk'])) ?>" rel="noopener" target="_blank">
            <?= icon('vk', 'size-4 text-gold') ?>ВКонтакте
          </a>
        </li>
      <?php endif ?>
      <?php /* Вход в админку для офиса — не пункт меню (в меню ровно четыре пункта, спека §5.2) */ ?>
      <li>
        <a class="flex items-center gap-2 rounded-btn border border-cream/30 px-3 py-1 font-semibold text-cream no-underline transition-colors hover:border-cream hover:bg-cream/10" href="/admin/" rel="nofollow">
          <?= icon('log-in', 'size-4 text-gold') ?><?= t('Вход для сотрудников') ?>
        </a>
      </li>
    </ul>
  </div>
</div>
