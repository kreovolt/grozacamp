<?php
declare(strict_types=1);

/**
 * Шаги-маршрут (макет B): номер в круге с пунктиром, между кругами — пунктирная тропа.
 * @var list<array{title:string, text:string}> $steps
 * @var int|null $level   уровень заголовков шагов, 3 по умолчанию
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\t;

$tag = 'h' . max(2, min(4, (int) ($level ?? 3)));
$last = count($steps) - 1;
?>
<ol class="<?= e(trim('space-y-7 ' . ($class ?? ''))) ?>" role="list">
  <?php foreach ($steps as $i => $step): ?>
    <li class="relative flex gap-5">
      <?php if ($i < $last): ?>
        <span class="absolute bottom-[-1.75rem] left-[1.4375rem] top-[3.25rem] border-l-2 border-dashed border-moss/45" aria-hidden="true"></span>
      <?php endif ?>
      <span class="grid size-12 shrink-0 place-items-center rounded-full border-2 border-dashed border-moss bg-sand font-display text-xl font-bold leading-none text-forest" aria-hidden="true"><?= $i + 1 ?></span>
      <div class="pt-1.5">
        <<?= $tag ?> class="font-display text-[1.375rem] font-semibold uppercase leading-tight tracking-[0.03em] text-ink"><?= t($step['title']) ?></<?= $tag ?>>
        <p class="mt-1.5 max-w-[46ch] leading-relaxed text-ink-soft"><?= t($step['text']) ?></p>
      </div>
    </li>
  <?php endforeach ?>
</ol>
