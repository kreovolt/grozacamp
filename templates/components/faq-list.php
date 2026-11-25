<?php
declare(strict_types=1);

/**
 * Вопросы и ответы — раскрывающиеся <details>, работают без JS.
 * Ответ — HTML из редактора админки (очищается по белому списку при сохранении), выводится как есть.
 * @var list<array{question:string, answer:string}> $items
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\icon;

if ($items === []) {
    return;
}
?>
<div class="<?= e(trim('border-t border-dashed border-moss/45 ' . ($class ?? ''))) ?>">
  <?php foreach ($items as $item): ?>
    <details class="group border-b border-dashed border-moss/45">
      <summary class="flex items-start justify-between gap-6 py-5 text-[1.0625rem] font-bold leading-snug text-ink transition-colors hover:text-rust-deep sm:text-lg">
        <span><?= e((string) $item['question']) ?></span>
        <?= icon('chevron-down', 'mt-0.5 size-6 text-rust-dark transition-transform duration-200 group-open:rotate-180') ?>
      </summary>
      <div class="richtext max-w-[62ch] pb-6 pr-8 leading-relaxed text-ink-soft">
        <?= (string) $item['answer'] ?>
      </div>
    </details>
  <?php endforeach ?>
</div>
