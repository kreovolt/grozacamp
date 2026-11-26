<?php
declare(strict_types=1);

/**
 * Полоса цифр на forest: крупное золотое значение + подпись. Только подтверждённые факты (спека §7.1, п. 2).
 * @var list<array{value:string,label:string}|string> $items  массивы или строки «значение|подпись» (формат texts home.stats)
 * @var string|null $label  подпись области для экранного диктора
 * @var string|null $class
 */
use function Groza\View\e;

$rows = [];
foreach ($items as $item) {
    if (is_string($item)) {
        [$value, $text] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');
        $item = ['value' => $value, 'label' => $text];
    }
    if (trim((string) ($item['value'] ?? '')) !== '') {
        $rows[] = $item;
    }
}
if ($rows === []) {
    return;
}
// Без линий-разделителей: пункты разделяет только воздух. Число колонок на десктопе = число пунктов, чтобы не было пустых ячеек
$columns = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'];
?>
<section class="<?= e(trim('on-dark bg-forest text-cream ' . ($class ?? ''))) ?>" aria-label="<?= e($label ?? 'Лагерь в цифрах') ?>">
  <div class="wrap">
    <ul class="grid grid-cols-2 gap-x-6 gap-y-7 py-8 sm:gap-x-10 sm:py-10 lg:gap-x-12 <?= $columns[min(count($rows), 5)] ?>" role="list">
      <?php foreach ($rows as $row): ?>
        <li class="flex flex-col gap-2 odd:last:col-span-2 lg:odd:last:col-span-1">
          <span class="tabular font-display text-[clamp(2rem,1.55rem+1.6vw,2.75rem)] font-bold leading-none text-gold"><?= e($row['value']) ?></span>
          <span class="text-[0.9375rem] leading-snug text-sage"><?= e($row['label'] ?? '') ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  </div>
</section>
