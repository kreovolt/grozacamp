<?php
declare(strict_types=1);

/**
 * Форма не отправилась (сбой почты, слишком много попыток, чужой источник запроса): телефон и WhatsApp.
 * @var int $status
 */
use function Groza\View\e;

$this->layout('layout', ['title' => 'Не удалось отправить — детский лагерь «Гроза»', 'noindex' => true]);
$lead = ($status ?? 503) === 429 ? 'Слишком много попыток подряд.' : 'Не получилось отправить форму.';
?>
<div class="wrap py-16 desk:py-24">
  <h1 class="font-display text-[clamp(2rem,1.4rem+2.5vw,3.25rem)] font-bold uppercase leading-[1.05]">Давайте по телефону</h1>
  <div class="mt-6 max-w-[56ch]">
    <?= $this->partial('partials/contact-fallback', ['intro' => $lead]) ?>
  </div>
</div>
