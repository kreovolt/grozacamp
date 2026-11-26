<?php
declare(strict_types=1);
/** Уведомление о cookie: показывается только при подключённой Метрике; «Понятно» запоминается в localStorage (frontend/js/cookie.js). */
use function Groza\View\typeset;
?>
<div class="fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4" data-cookie-notice role="region" aria-label="Уведомление о cookie" hidden>
  <div class="mx-auto flex max-w-[44rem] flex-col gap-3 rounded-[6px] border border-cream/20 bg-forest p-4 text-[0.9375rem] leading-snug text-cream shadow-lg sm:flex-row sm:items-center sm:gap-5">
    <p class="flex-1"><?= typeset('Сайт использует cookie и Яндекс.Метрику, чтобы понимать, чем он полезен. Подробнее — в <a class="underline underline-offset-4 hover:text-gold" href="/policy/">политике обработки данных</a>.') ?></p>
    <button type="button" class="shrink-0 rounded-[4px] bg-rust-dark px-5 py-2.5 font-display text-[0.9375rem] font-semibold uppercase tracking-[0.08em] text-white hover:bg-rust" data-cookie-accept>Понятно</button>
  </div>
</div>
