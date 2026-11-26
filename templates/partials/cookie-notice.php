<?php
declare(strict_types=1);
/** Уведомление о cookie: показывается только при подключённой Метрике; «Понятно» запоминается в localStorage (frontend/js/cookie.js). */
use function Groza\View\typeset;
?>
<div class="fixed inset-x-0 bottom-0 z-50 p-2 sm:p-4" data-cookie-notice role="region" aria-label="Уведомление о cookie" hidden>
  <?php /* На телефоне — компактно: текст мельче, «Понятно» справа от текста (с 360 px), уведомление не закрывает треть экрана */ ?>
  <div class="mx-auto flex max-w-[44rem] flex-col gap-2.5 rounded-[6px] border border-cream/20 bg-forest px-3.5 py-3 text-[0.8125rem] leading-snug text-cream shadow-lg min-[360px]:flex-row min-[360px]:items-center min-[360px]:gap-3 sm:gap-5 sm:p-4 sm:text-[0.9375rem]">
    <p class="flex-1"><?= typeset('Сайт использует cookie и Яндекс.Метрику, чтобы понимать, чем он полезен. Подробнее — в <a class="underline underline-offset-4 hover:text-gold" href="/policy/">политике обработки данных</a>.') ?></p>
    <button type="button" class="min-h-11 shrink-0 rounded-[4px] bg-rust-dark px-4 py-2 font-display sm:px-5 sm:py-2.5 text-[0.9375rem] font-semibold uppercase tracking-[0.08em] text-white hover:bg-rust" data-cookie-accept>Понятно</button>
  </div>
</div>
