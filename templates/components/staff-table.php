<?php
declare(strict_types=1);

/**
 * Сотрудники таблицей. columns = full: ФИО, должность, образование, направление подготовки / специальность, квалификация (п. 6 а);
 * short: ФИО и должность (руководство, ответственные за доступность). Узко — строка становится карточкой с подписями полей:
 * полная таблица (5 колонок) — уже 1200 px (рядом с оглавлением ей тесно: колонки в 90 px и переносы «Информа-ция»),
 * короткая — уже 768 px. Переносов по слогам в таблице нет: ФИО и «Информация обновляется» не делим.
 * Пустое поле — «Информация обновляется».
 * @var list<array<string,mixed>> $members
 * @var string $columns full|short
 */
use function Groza\View\e;
use function Groza\View\pending;
use function Groza\View\t;

$full = ($columns ?? 'full') === 'full';
// Классы карточного вида целиком, строками: Tailwind находит их в исходнике (собранные из кусков он бы не увидел)
$card = $full
    ? [
        'thead' => 'max-desk:sr-only',
        'tr' => 'max-desk:block max-desk:py-3 sm:max-desk:grid sm:max-desk:grid-cols-2 sm:max-desk:gap-x-8',
        'td' => 'max-desk:block max-desk:px-0 max-desk:py-1 max-desk:before:block max-desk:before:text-[0.75rem] max-desk:before:uppercase max-desk:before:tracking-[0.06em] max-desk:before:text-ink-soft max-desk:before:content-[attr(data-label)]',
    ]
    : [
        'thead' => 'max-md:sr-only',
        'tr' => 'max-md:block max-md:py-3',
        'td' => 'max-md:block max-md:px-0 max-md:py-1 max-md:before:block max-md:before:text-[0.75rem] max-md:before:uppercase max-md:before:tracking-[0.06em] max-md:before:text-ink-soft max-md:before:content-[attr(data-label)]',
    ];
$cols = [['name', 'ФИО'], ['role', 'Должность']];
if ($full) {
    array_push($cols, ['education', 'Образование'], ['specialty', 'Направление подготовки / специальность'], ['qualification', 'Квалификация']);
}
?>
<?php /* Обёртка с прокруткой — страховка: длинное слово в реальных данных не должно растягивать страницу (рядом с оглавлением колонка узкая).
   Tab доходит до неё, только когда есть что прокручивать (frontend/js/scroll-regions.js) */ ?>
<div class="table-scroll" role="region" aria-label="<?= e($full ? 'Таблица: педагогический и вожатский состав' : 'Таблица: сотрудники') ?>" data-scroll-region>
<table class="staff-table w-full border-collapse text-left text-[0.9375rem] leading-snug">
  <thead class="<?= $card['thead'] ?>">
    <tr class="border-b-2 border-forest/70 align-bottom">
      <?php foreach ($cols as [, $label]): ?>
        <th scope="col" class="px-2.5 pb-2 pt-1 font-display text-[0.75rem] font-semibold uppercase tracking-[0.04em] text-ink first:pl-0"><?= t($label) ?></th>
      <?php endforeach ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($members as $m): ?>
      <tr data-staff class="border-b border-dashed border-moss/45 align-top <?= $card['tr'] ?>">
        <?php foreach ($cols as [$field, $label]): ?>
          <td data-label="<?= e($label) ?>" class="px-2.5 py-2.5 first:pl-0 <?= $card['td'] ?> <?= $field === 'name' ? 'font-semibold text-ink' : 'text-ink-soft' ?>"><?= pending(is_string($m[$field] ?? null) ? $m[$field] : '') ?></td>
        <?php endforeach ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
</div>
