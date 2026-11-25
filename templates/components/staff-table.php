<?php
declare(strict_types=1);

/**
 * Сотрудники таблицей. columns = full: ФИО, должность, образование, направление подготовки / специальность, квалификация (п. 6 а);
 * short: ФИО и должность (руководство, ответственные за доступность). На телефоне строка — карточка с подписями полей.
 * Пустое поле — «Информация обновляется».
 * @var list<array<string,mixed>> $members
 * @var string $columns full|short
 */
use function Groza\View\e;
use function Groza\View\pending;

$full = ($columns ?? 'full') === 'full';
$cols = [['name', 'ФИО'], ['role', 'Должность']];
if ($full) {
    array_push($cols, ['education', 'Образование'], ['specialty', 'Направление подготовки / специальность'], ['qualification', 'Квалификация']);
}
?>
<table class="staff-table w-full border-collapse text-left text-[0.9375rem] leading-snug">
  <thead class="max-md:sr-only">
    <tr class="border-b-2 border-forest/70 align-bottom">
      <?php foreach ($cols as [, $label]): ?>
        <th scope="col" class="px-3 pb-2 pt-1 font-display text-[0.8125rem] font-semibold uppercase tracking-[0.08em] text-ink first:pl-0"><?= e($label) ?></th>
      <?php endforeach ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($members as $m): ?>
      <tr data-staff class="border-b border-dashed border-moss/45 align-top max-md:block max-md:py-3">
        <?php foreach ($cols as [$field, $label]): ?>
          <td data-label="<?= e($label) ?>" class="px-3 py-2.5 first:pl-0 max-md:block max-md:px-0 max-md:py-1 max-md:before:block max-md:before:text-[0.75rem] max-md:before:uppercase max-md:before:tracking-[0.06em] max-md:before:text-ink-soft max-md:before:content-[attr(data-label)] <?= $field === 'name' ? 'font-semibold text-ink' : 'text-ink-soft' ?>"><?= pending(is_string($m[$field] ?? null) ? $m[$field] : '') ?></td>
        <?php endforeach ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
