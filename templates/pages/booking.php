<?php
declare(strict_types=1);

/**
 * «Купить путёвку» (спека §7.2): герой с телефоном и WhatsApp, смены (#smeny), заявка (#zayavka),
 * что входит в стоимость, несколько частых вопросов.
 * @var list<array<string,mixed>> $shifts
 * @var array<string,array<string,mixed>> $venues
 * @var array<string,string> $values
 * @var array<string,string> $errors
 * @var array<string,mixed> $options
 * @var array<string,string>|null $guard
 * @var list<string> $included
 * @var list<array{question:string,answer:string}> $faq
 * @var bool $hasErrors
 * @var array<string,mixed> $settings
 */
use Groza\Pages\HomePage;
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\t;
use function Groza\View\tel;

$this->layout('layout', ['page' => 'booking']);

$section = 'py-16 sm:py-20 desk:py-24';
$phone = trim((string) ($settings['phone'] ?? ''));
$wa = preg_replace('/\D+/', '', (string) ($settings['whatsapp'] ?? '')) ?? '';
$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="booking-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap grid grid-cols-1 gap-10 pb-14 pt-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-end lg:gap-16 desk:pb-20 desk:pt-12">
    <div>
      <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => 'Купить путёвку']], 'tone' => 'dark', 'class' => 'mb-8']) ?>
      <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="booking-title">Купить путёвку</h1>
      <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= t('Выберите смену и оставьте заявку: офис перезвонит в рабочее время и ответит на вопросы. Если удобнее, позвоните или напишите сами.') ?></p>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <?= $this->partial('components/button', ['label' => 'Выбрать смену', 'href' => '#smeny', 'size' => 'lg']) ?>
        <?= $this->partial('components/button', ['label' => 'Сразу к заявке', 'href' => '#zayavka', 'size' => 'lg', 'variant' => 'outline-light']) ?>
      </div>
    </div>

    <div class="border-t border-dashed border-sage/40 pt-8 lg:border-l lg:border-t-0 lg:pb-1 lg:pl-10 lg:pt-0">
      <p class="text-[1.0625rem] font-semibold text-cream/90"><?= t('Быстрее всего — по телефону') ?></p>
      <?php if ($phone !== ''): ?>
        <a class="tabular mt-3 inline-block whitespace-nowrap font-display text-[clamp(1.875rem,1.5rem+1.4vw,2.5rem)] font-semibold leading-none tracking-[0.02em] text-cream no-underline hover:text-white" href="<?= e(tel($phone)) ?>"><?= t(Phone::pretty($phone)) ?></a>
      <?php endif ?>
      <?php if ($hours !== []): ?>
        <p class="mt-3 flex items-center gap-2 text-[0.9375rem] text-sage"><?= icon('clock', 'size-4 text-gold') ?><span><?= t(implode(', ', $hours)) ?></span></p>
      <?php endif ?>
      <div class="mt-6 flex flex-col gap-3 sm:flex-row">
        <?php if ($phone !== ''): ?>
          <?= $this->partial('components/button', ['label' => 'Позвонить', 'href' => tel($phone), 'icon' => 'phone', 'variant' => 'outline-light']) ?>
        <?php endif ?>
        <?php if ($wa !== ''): ?>
          <?= $this->partial('components/button', [
              'label' => 'Написать в WhatsApp',
              'href' => 'https://wa.me/' . $wa,
              'icon' => 'message-circle',
              'variant' => 'outline-light',
              'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
          ]) ?>
        <?php endif ?>
      </div>
    </div>
  </div>
</section>

<section class="<?= $section ?>" id="smeny" data-block="shifts" aria-labelledby="smeny-title">
  <div class="wrap">
    <?= $this->partial('components/section-head', [
        'title' => 'Смены',
        'lead' => 'Даты, возраст и цена — на карточке. Проживание, питание и трансфер из Перми уже входят в стоимость.',
        'id' => 'smeny-title',
    ]) ?>
    <?php if ($shifts !== []): ?>
      <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 desk:gap-8" data-reveal>
        <?php foreach ($shifts as $shift): ?>
          <?= $this->partial('components/shift-card', [
              'shift' => $shift,
              'venue' => $venues[(string) ($shift['venue'] ?? '')] ?? null,
              'bookingHref' => HomePage::bookingHref($shift),
          ]) ?>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="mt-10 flex flex-col items-start gap-5 rounded-card border-2 border-dashed border-moss/45 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <p class="max-w-[48ch] text-lg font-semibold leading-snug text-ink"><?= t('Даты скоро. Оставьте заявку — позвоним первыми.') ?></p>
        <?= $this->partial('components/button', ['label' => 'Оставить заявку', 'href' => '#zayavka', 'class' => 'shrink-0']) ?>
      </div>
    <?php endif ?>
  </div>
</section>

<section class="bg-sand-deep <?= $section ?> scroll-mt-4" id="zayavka" data-block="request" aria-labelledby="zayavka-title">
  <div class="wrap grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
    <div>
      <?= $this->partial('components/section-head', [
          'title' => 'Оставьте заявку',
          'lead' => 'Несколько коротких полей. Мы перезвоним в рабочее время, расскажем про смену и ответим на вопросы.',
          'id' => 'zayavka-title',
      ]) ?>
      <ul class="mt-8 space-y-4 border-t border-dashed border-moss/50 pt-6 text-ink" role="list">
        <li class="flex gap-3.5 leading-snug"><?= icon('check', 'mt-0.5 size-5 shrink-0 text-moss') ?><span><?= t('Смену можно не выбирать: отметьте «Пока не знаю», подберём вместе.') ?></span></li>
        <?php if ($phone !== ''): ?>
          <li class="flex gap-3.5 leading-snug"><?= icon('phone', 'mt-0.5 size-5 shrink-0 text-moss') ?><span><?= t('Не хотите ждать звонка? Наберите') ?> <a class="tabular whitespace-nowrap font-semibold text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="<?= e(tel($phone)) ?>"><?= t(Phone::pretty($phone)) ?></a>.</span></li>
        <?php endif ?>
      </ul>
    </div>
    <div class="rounded-card bg-paper p-6 shadow-card sm:p-8">
      <?= $this->partial('forms/booking', ['values' => $values, 'errors' => $errors, 'options' => $options, 'guard' => $guard]) ?>
    </div>
  </div>
</section>

<?php if ($included !== []): ?>
  <section class="<?= $section ?>" data-block="included" aria-labelledby="included-title">
    <div class="wrap">
      <?= $this->partial('components/section-head', ['title' => 'Что входит в стоимость', 'id' => 'included-title']) ?>
      <?= $this->partial('components/included', ['items' => $included, 'class' => 'mt-10']) ?>
    </div>
  </section>
<?php endif ?>

<?php if ($faq !== []): ?>
  <section class="bg-paper <?= $section ?>" data-block="faq" aria-labelledby="faq-title">
    <div class="wrap grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
      <?= $this->partial('components/section-head', ['title' => 'Частые вопросы', 'id' => 'faq-title']) ?>
      <?= $this->partial('components/faq-list', ['items' => $faq]) ?>
    </div>
  </section>
<?php endif ?>
