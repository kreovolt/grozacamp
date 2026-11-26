<?php
declare(strict_types=1);

/**
 * Кнопка «Наверх» в правом нижнем углу — обычная ссылка на начало страницы (#top — это body).
 * Видна только после прокрутки (frontend/js/to-top.js); без JS её нет — это лишь удобство.
 */
use function Groza\View\icon;
?>
<a class="to-top on-dark grid size-12 place-items-center rounded-full border border-cream/25 bg-forest text-cream no-underline shadow-pop hover:bg-forest-deep" href="#top" aria-label="Наверх" data-to-top>
  <?= icon('arrow-up', 'size-5') ?>
</a>
