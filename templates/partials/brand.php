<?php
declare(strict_types=1);

/**
 * Шеврон + «ГРОЗА» + подпись. Ссылка на главную.
 * Шеврон — только мелко (спека §6: на нём силуэт автомата, крупно не показываем).
 * @var string $currentPath
 * @var bool|null $stacked  подпись в две строки на любой ширине (подвал)
 */
$isHome = ($currentPath ?? '') === '/';
$stacked = !empty($stacked);
?>
<a class="group mr-auto flex min-h-11 shrink-0 items-center gap-3 text-cream no-underline" href="/"<?= $isHome ? ' aria-current="page"' : '' ?>>
  <img class="h-10 w-auto desk:h-[52px]" src="/assets/logo-header.png" alt="" width="44" height="52">
  <span class="flex flex-col">
    <span class="font-display text-[1.375rem] font-bold uppercase leading-none tracking-brand desk:text-[1.625rem]">Гроза</span>
    <span class="mt-1 text-[0.6875rem] font-semibold uppercase leading-tight tracking-[0.12em] text-sage <?= $stacked ? '' : 'sm:whitespace-nowrap ' ?>xl:text-xs">
      <?php if ($stacked): ?>
        детский лагерь<br>Пермский край
      <?php else: ?>
        детский лагерь<span class="hidden sm:inline"> · </span><br class="sm:hidden">Пермский край
      <?php endif ?>
    </span>
  </span>
</a>
