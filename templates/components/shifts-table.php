<?php
declare(strict_types=1);

/**
 * Смены года из данных смен. mode = dates («Деятельность», п. 8 б): смена, даты, дней, возраст, база;
 * prices («Услуги», п. 10 б): смена, даты, дней, путёвка, стоимость дня («≈ 2 857 ₽ в день»). На телефоне строка — карточка с подписями.
 * @var list<array{title:string,note:string,dates:string,days:int,ages:string,venue:string,price:?int,perDay:?int}> $shifts
 * @var string $mode
 * @var string $caption
 */
use Groza\Pages\SvedeniyaPage;

use function Groza\View\e;
use function Groza\View\pending;
use function Groza\View\t;

$prices = ($mode ?? 'dates') === 'prices';
$cols = $prices
    ? ['Смена', 'Даты', 'Дней', 'Путёвка', 'Стоимость дня']
    : ['Смена', 'Даты', 'Дней', 'Возраст', 'База'];
$cell = 'px-3 py-2.5 first:pl-0 max-md:block max-md:px-0 max-md:py-1 max-md:before:block max-md:before:text-[0.75rem] max-md:before:uppercase max-md:before:tracking-[0.06em] max-md:before:text-ink-soft max-md:before:content-[attr(data-label)]';
?>
<div class="table-scroll" role="region" aria-label="<?= e($caption) ?>" data-scroll-region>
<table class="shifts-table w-full border-collapse text-left text-[0.9375rem] leading-snug">
  <caption class="sr-only"><?= t($caption) ?></caption>
  <thead class="max-md:sr-only">
    <tr class="border-b-2 border-forest/70 align-bottom">
      <?php foreach ($cols as $label): ?>
        <th scope="col" class="px-3 pb-2 pt-1 font-display text-[0.8125rem] font-semibold uppercase tracking-[0.08em] text-ink first:pl-0"><?= t($label) ?></th>
      <?php endforeach ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($shifts as $s): ?>
      <tr class="border-b border-dashed border-moss/45 align-top max-md:block max-md:py-3">
        <td data-label="<?= e($cols[0]) ?>" class="<?= $cell ?> font-semibold text-ink"><?= t((string) ($s['title'] ?? '')) ?></td>
        <td data-label="<?= e($cols[1]) ?>" class="<?= $cell ?> whitespace-nowrap max-md:whitespace-normal"><?= t($s['dates']) ?></td>
        <td data-label="<?= e($cols[2]) ?>" class="<?= $cell ?> tabular"><?= (int) $s['days'] ?></td>
        <?php if ($prices): ?>
          <td data-label="<?= e($cols[3]) ?>" class="<?= $cell ?> tabular whitespace-nowrap font-semibold text-ink"><?= $s['price'] !== null ? t(SvedeniyaPage::rub($s['price'])) : pending('') ?><?php if ($s['note'] !== ''): ?><span class="block whitespace-normal text-[0.875rem] font-normal text-ink-soft"><?= t($s['note']) ?></span><?php endif ?></td>
          <td data-label="<?= e($cols[4]) ?>" class="<?= $cell ?> tabular"><?= $s['perDay'] !== null ? t(SvedeniyaPage::perDay($s['perDay'])) : pending('') ?></td>
        <?php else: ?>
          <td data-label="<?= e($cols[3]) ?>" class="<?= $cell ?>"><?= t($s['ages']) ?></td>
          <td data-label="<?= e($cols[4]) ?>" class="<?= $cell ?>"><?= t($s['venue']) ?></td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
</div>
