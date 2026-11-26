<?php
declare(strict_types=1);

/**
 * Форма не отправилась (сбой почты, слишком много попыток, чужой источник запроса): телефон и WhatsApp.
 * @var int $status
 */
use function Groza\View\e;
use function Groza\View\t;

$this->layout('layout', ['page' => 'form-error', 'noindex' => true]);
$lead = ($status ?? 503) === 429 ? 'Слишком много попыток подряд.' : 'Не получилось отправить форму.';
?>
<?php /* Как у остальных внутренних страниц: лесная полоса с заголовком, ниже — песочный блок с телефоном и WhatsApp */ ?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="fe-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap pb-14 pt-12 desk:pb-20 desk:pt-16">
    <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="fe-title"><?= t('Давайте по телефону') ?></h1>
  </div>
</section>

<div class="wrap py-12 sm:py-16">
  <div class="max-w-[56ch]">
    <?= $this->partial('partials/contact-fallback', ['intro' => $lead]) ?>
  </div>
</div>
