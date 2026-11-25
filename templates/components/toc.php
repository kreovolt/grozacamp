<?php
declare(strict_types=1);

/**
 * Оглавление страницы: ссылки на якоря разделов. Две версии разметки, переключаются CSS (скрипт не нужен — поэтому
 * и сдвига вёрстки после загрузки нет): на телефоне — раскрывающийся список (длинный, больше 8 пунктов, свёрнут;
 * короткий открыт), на широком экране — всегда открытый столбец; закрепляется при прокрутке (sticky).
 * @var list<array{id:string,label:string}> $items
 * @var string|null $title   подпись, «Содержание» по умолчанию
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\icon;

if (($items ?? []) === []) {
    return;
}
$caption = (string) ($title ?? 'Содержание');
$long = count($items) > 8;
$list = static function () use ($items): string {
    $html = '';
    foreach ($items as $item) {
        $html .= '<li class="border-b border-dashed border-moss/45"><a class="block py-2.5 leading-snug text-ink-soft no-underline transition-colors hover:text-rust-deep" href="#'
            . e($item['id']) . '">' . e($item['label']) . '</a></li>';
    }

    return $html;
};
?>
<nav data-toc class="<?= e(trim('self-start lg:sticky lg:top-6 ' . ($class ?? ''))) ?>" aria-label="<?= e($caption) ?>">
  <details class="group rounded-card bg-paper p-5 shadow-card lg:hidden"<?= $long ? '' : ' open' ?>>
    <summary class="flex cursor-pointer list-none items-center justify-between font-display text-[1.125rem] font-semibold uppercase tracking-[0.04em] text-ink [&::-webkit-details-marker]:hidden">
      <span><?= e($caption) ?></span>
      <?= icon('chevron-down', 'size-5 text-rust-dark transition-transform duration-200 group-open:rotate-180') ?>
    </summary>
    <ol class="mt-3 border-t border-dashed border-moss/45" role="list"><?= $list() ?></ol>
  </details>
  <div class="hidden lg:block">
    <p class="font-display text-[1.125rem] font-semibold uppercase tracking-[0.04em] text-ink"><?= e($caption) ?></p>
    <ol class="mt-4 border-t border-dashed border-moss/45" role="list"><?= $list() ?></ol>
  </div>
</nav>
