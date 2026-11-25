<?php
declare(strict_types=1);

/**
 * Страница не найдена: короткое объяснение и три шага дальше. Не индексируется.
 * @var array<string,mixed> $settings
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\tel;

$this->layout('layout', ['title' => 'Страница не найдена — детский лагерь «Гроза», Пермь', 'noindex' => true]);
$phone = trim((string) ($settings['phone'] ?? ''));
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" aria-labelledby="nf-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap py-16 sm:py-20 desk:py-28">
    <p class="tabular font-display text-[clamp(5rem,3rem+10vw,10rem)] font-bold leading-none tracking-[0.02em] text-gold" aria-hidden="true">404</p>
    <h1 class="mt-4 font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="nf-title">Такой страницы нет</h1>
    <p class="mt-5 max-w-[46ch] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg">Возможно, ссылка устарела или в адресе опечатка. Выберите смену или позвоните в офис, подскажем.</p>
    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
      <?= $this->partial('components/button', ['label' => 'Выбрать смену', 'href' => '/booking/', 'size' => 'lg']) ?>
      <?= $this->partial('components/button', ['label' => 'Контакты', 'href' => '/contacts/', 'size' => 'lg', 'variant' => 'outline-light']) ?>
      <?= $this->partial('components/button', ['label' => 'На главную', 'href' => '/', 'size' => 'lg', 'variant' => 'outline-light']) ?>
    </div>
    <?php if ($phone !== ''): ?>
      <p class="mt-8 text-cream/90">Телефон офиса: <a class="tabular whitespace-nowrap font-semibold text-cream underline decoration-gold/60 underline-offset-4 hover:decoration-gold" href="<?= e(tel($phone)) ?>"><?= e(Phone::pretty($phone)) ?></a></p>
    <?php endif ?>
  </div>
</section>
