<?php
declare(strict_types=1);

/**
 * «Контакты»: слева способы связи крупно, справа офис — адрес, часы и ссылка на карту. Пустая настройка скрывает пункт.
 * @var string $mapUrl
 * @var array<string,mixed> $settings
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\safe_url;
use function Groza\View\t;
use function Groza\View\tel;

$this->layout('layout', ['page' => 'contacts']);

$phone = trim((string) ($settings['phone'] ?? ''));
$wa = preg_replace('/\D+/', '', (string) ($settings['whatsapp'] ?? '')) ?? '';
$email = trim((string) ($settings['email'] ?? ''));
$vk = safe_url($settings['vk'] ?? '');
$address = trim((string) ($settings['officeAddress'] ?? ''));
$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
$row = 'flex items-start gap-4 border-b border-dashed border-moss/45 py-5';
$label = 'text-[0.875rem] font-semibold uppercase tracking-[0.08em] text-ink-soft';
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="contacts-title">
  <div class="wrap pb-14 pt-8 desk:pb-20 desk:pt-12">
    <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => 'Контакты']], 'tone' => 'dark', 'class' => 'mb-8']) ?>
    <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="contacts-title">Контакты</h1>
    <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= t('Быстрее всего ответим по телефону или в WhatsApp. Письма читаем в рабочее время.') ?></p>
  </div>
</section>

<div class="wrap grid grid-cols-1 gap-10 py-12 sm:py-16 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:gap-16 desk:py-20" data-block="contacts">
  <ul class="border-t border-dashed border-moss/45" role="list">
    <?php if ($phone !== ''): ?>
      <li class="<?= $row ?>">
        <?= icon('phone', 'mt-1 size-6 text-moss') ?>
        <div>
          <p class="<?= $label ?>">Телефон</p>
          <a class="tap tabular mt-1 inline-block whitespace-nowrap font-display text-[clamp(1.75rem,1.4rem+1.4vw,2.5rem)] font-semibold leading-tight tracking-[0.02em] text-ink no-underline hover:text-rust-deep" href="<?= e(tel($phone)) ?>"><?= t(Phone::pretty($phone)) ?></a>
        </div>
      </li>
    <?php endif ?>
    <?php if ($wa !== ''): ?>
      <li class="<?= $row ?>">
        <?= icon('message-circle', 'mt-1 size-6 text-moss') ?>
        <div>
          <p class="<?= $label ?>">WhatsApp</p>
          <a class="tap tabular mt-1 inline-block whitespace-nowrap text-xl font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= t(Phone::pretty((string) $settings['whatsapp'])) ?></a>
        </div>
      </li>
    <?php endif ?>
    <?php if ($email !== ''): ?>
      <li class="<?= $row ?>">
        <?= icon('mail', 'mt-1 size-6 text-moss') ?>
        <div>
          <p class="<?= $label ?>">Почта</p>
          <a class="tap mt-1 inline-block break-all text-xl font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        </div>
      </li>
    <?php endif ?>
    <?php if ($vk !== ''): ?>
      <li class="<?= $row ?>">
        <?= icon('vk', 'mt-1 size-6 text-moss') ?>
        <div>
          <p class="<?= $label ?>">ВКонтакте</p>
          <a class="tap mt-1 inline-block break-all text-xl font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="<?= e($vk) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $vk) ?? $vk) ?></a>
          <p class="mt-1 text-[0.9375rem] text-ink-soft"><?= t('Каждый день выкладываем фото и видео со смены.') ?></p>
        </div>
      </li>
    <?php endif ?>
  </ul>

  <?php if ($address !== '' || $hours !== []): ?>
    <aside class="on-dark relative isolate self-start overflow-hidden rounded-card bg-forest p-6 text-cream sm:p-8" aria-labelledby="office-title">
      <h2 class="font-display text-[1.5rem] font-bold uppercase leading-tight tracking-[0.02em]" id="office-title">Офис</h2>
      <?php if ($address !== ''): ?>
        <p class="mt-4 flex items-start gap-3 text-[1.0625rem] leading-snug"><?= icon('map-pin', 'mt-0.5 size-5 shrink-0 text-gold') ?><span><?= t($address) ?></span></p>
      <?php endif ?>
      <?php if ($hours !== []): ?>
        <ul class="mt-4 space-y-1.5" role="list">
          <?php foreach ($hours as $i => $h): ?>
            <li class="flex items-start gap-3 text-[1.0625rem] leading-snug"><?= icon('clock', 'mt-0.5 size-5 shrink-0 ' . ($i === 0 ? 'text-gold' : 'text-gold/0')) ?><span class="tabular"><?= t((string) $h) ?></span></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <?php if ($mapUrl !== ''): ?>
        <?= $this->partial('components/button', [
            'label' => 'Открыть на карте',
            'href' => $mapUrl,
            'icon' => 'map-pin',
            'variant' => 'outline-light',
            'class' => 'mt-6 w-full sm:w-auto',
            'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
        ]) ?>
      <?php endif ?>
    </aside>
  <?php endif ?>
</div>
