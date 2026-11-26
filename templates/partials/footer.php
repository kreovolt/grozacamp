<?php
declare(strict_types=1);

/**
 * Подвал (спека §5.3): «Лагерю» · «Сведения» (6 разделов + 5 подразделов) · «Контакты»; внизу политика, правила, ©.
 * @var list<array<string,mixed>> $nav
 * @var array<string,mixed> $settings
 * @var string $year
 */
use Groza\Content\Nav;
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\safe_url;
use function Groza\View\t;
use function Groza\View\tel;

$svedeniya = null;
foreach ($nav as $item) {
    if ($item['kind'] === 'dropdown') {
        $svedeniya = $item;
    }
}
$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
$heading = 'mb-5 font-display text-[0.875rem] font-semibold uppercase tracking-[0.16em] text-gold';
?>
<footer class="on-dark relative overflow-hidden bg-forest text-cream" id="podval">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-x-0 top-0 h-56 w-full text-sand opacity-[0.07]']) ?>
  <div class="wrap relative grid gap-x-10 gap-y-12 pb-12 pt-14 md:grid-cols-2 desk:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)_minmax(0,1.35fr)_minmax(0,1.1fr)] desk:pt-16">
    <div class="flex flex-col items-start gap-5">
      <?= $this->partial('partials/brand', ['stacked' => true]) ?>
      <p class="max-w-[30ch] text-[0.9375rem] leading-relaxed text-sage"><?= t('Детский лагерь приключений в Пермском крае. Смены летом и в каникулы.') ?></p>
    </div>

    <nav aria-labelledby="footer-camp">
      <h2 class="<?= $heading ?>" id="footer-camp">Лагерю</h2>
      <ul class="space-y-3" role="list">
        <?php foreach (Nav::footer() as $link): ?>
          <li><a class="link-quiet" href="<?= e($link['href']) ?>"><?= t($link['label']) ?></a></li>
        <?php endforeach ?>
      </ul>
    </nav>

    <?php if ($svedeniya !== null): ?>
      <nav aria-labelledby="footer-svedeniya">
        <h2 class="<?= $heading ?>" id="footer-svedeniya">
          <a class="no-underline hover:underline hover:decoration-gold" href="<?= e($svedeniya['href']) ?>">Сведения</a>
        </h2>
        <ul class="space-y-3 text-[0.9375rem] leading-snug" role="list">
          <?php foreach ($svedeniya['children'] as $section): ?>
            <li>
              <a class="link-quiet" href="<?= e($section['href']) ?>"><?= t($section['label']) ?></a>
              <?php if (!empty($section['children'])): ?>
                <ul class="ml-1 mt-3 space-y-2 border-l border-dashed border-sage/40 pl-4" role="list">
                  <?php foreach ($section['children'] as $sub): ?>
                    <li><a class="link-quiet text-sage" href="<?= e($sub['href']) ?>"><?= t($sub['label']) ?></a></li>
                  <?php endforeach ?>
                </ul>
              <?php endif ?>
            </li>
          <?php endforeach ?>
        </ul>
      </nav>
    <?php endif ?>

    <div>
      <h2 class="<?= $heading ?>">Контакты</h2>
      <ul class="space-y-4 text-[0.9375rem] leading-snug" role="list">
        <?php if (!empty($settings['phone'])): ?>
          <li>
            <a class="tabular whitespace-nowrap font-display text-2xl font-semibold leading-none tracking-[0.02em] text-cream no-underline hover:text-white" href="<?= e(tel($settings['phone'])) ?>"><?= t(Phone::pretty($settings['phone'])) ?></a>
          </li>
        <?php endif ?>
        <?php if (!empty($settings['whatsapp'])): ?>
          <li>
            <a class="link-quiet inline-flex items-center gap-2.5" href="https://wa.me/<?= e(ltrim((string) $settings['whatsapp'], '+')) ?>" rel="noopener" target="_blank">
              <?= icon('message-circle', 'size-[1.125rem] text-gold') ?><span><?= t('WhatsApp ' . Phone::pretty((string) $settings['whatsapp'])) ?></span>
            </a>
          </li>
        <?php endif ?>
        <?php if (!empty($settings['email'])): ?>
          <li>
            <a class="link-quiet inline-flex items-center gap-2.5" href="mailto:<?= e($settings['email']) ?>">
              <?= icon('mail', 'size-[1.125rem] text-gold') ?><?= t($settings['email']) ?>
            </a>
          </li>
        <?php endif ?>
        <?php if (!empty($settings['officeAddress'])): ?>
          <li class="flex gap-2.5 text-cream/90">
            <?= icon('map-pin', 'mt-0.5 size-[1.125rem] text-gold') ?>
            <span><?= t('Офис: ' . $settings['officeAddress']) ?></span>
          </li>
        <?php endif ?>
        <?php if ($hours): ?>
          <li class="flex gap-2.5 text-cream/90">
            <?= icon('clock', 'mt-0.5 size-[1.125rem] text-gold') ?>
            <span><?= implode('<br>', array_map(static fn (string $h): string => t($h), $hours)) ?></span>
          </li>
        <?php endif ?>
        <?php if (safe_url($settings['vk'] ?? '') !== '' || safe_url($settings['youtube'] ?? '') !== ''): ?>
          <li class="flex flex-wrap gap-x-6 gap-y-3 pt-1">
            <?php if (safe_url($settings['vk'] ?? '') !== ''): ?>
              <a class="link-quiet inline-flex items-center gap-2.5" href="<?= e(safe_url($settings['vk'])) ?>" rel="noopener" target="_blank"><?= icon('vk', 'size-[1.125rem] text-gold') ?>ВКонтакте</a>
            <?php endif ?>
            <?php if (safe_url($settings['youtube'] ?? '') !== ''): ?>
              <a class="link-quiet inline-flex items-center gap-2.5" href="<?= e(safe_url($settings['youtube'])) ?>" rel="noopener" target="_blank"><?= icon('youtube', 'size-[1.125rem] text-gold') ?>YouTube</a>
            <?php endif ?>
          </li>
        <?php endif ?>
        <li class="pt-1">
          <a class="inline-flex items-center gap-2 font-display text-[0.8125rem] font-semibold uppercase tracking-[0.12em] text-gold no-underline hover:text-cream" href="/contacts/">
            Все контакты <?= icon('arrow-right', 'size-4') ?>
          </a>
        </li>
      </ul>
    </div>
  </div>

  <div class="relative border-t border-cream/[0.12]">
    <div class="wrap flex flex-col gap-3 py-6 text-[0.875rem] text-sage sm:flex-row sm:items-center sm:justify-between">
      <p>© Детский лагерь «Гроза», <?= e((string) $year) ?></p>
      <ul class="flex flex-wrap gap-x-6 gap-y-2" role="list">
        <?php foreach (Nav::legal() as $link): ?>
          <li><a class="link-quiet text-sage" href="<?= e($link['href']) ?>"><?= t($link['label']) ?></a></li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
</footer>
