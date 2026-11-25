<?php
declare(strict_types=1);

/**
 * Хаб раздела сведений: иерархический список всех разделов и подразделов (п. 12 приказа).
 * @var string $title
 * @var string $description
 * @var list<array{label:string,href?:string}> $crumbs
 * @var list<array{id:string,label:string}> $toc
 * @var string|null $updated
 * @var string $updatedText
 * @var list<array{label:string,href:string,children?:list<array{label:string,href:string}>}> $tree
 */
use function Groza\View\e;
use function Groza\View\icon;

$this->layout('pages/svedeniya/_frame', compact('title', 'description', 'crumbs', 'toc', 'updated', 'updatedText'));
?>
<p class="max-w-[60ch] text-lg leading-relaxed text-ink-soft">Официальная информация о лагере в том порядке, который установлен приказом Минпросвещения России от 14.03.2025 № 201 для сайтов организаций отдыха детей и их оздоровления.</p>
<nav class="mt-8" aria-label="Разделы сведений">
  <ol class="border-t border-dashed border-moss/45" role="list">
    <?php foreach ($tree as $section): ?>
      <li class="border-b border-dashed border-moss/45 py-4">
        <a class="inline-flex items-center gap-2 font-display text-[1.25rem] font-semibold uppercase leading-snug tracking-[0.03em] text-ink no-underline hover:text-rust-deep" href="<?= e($section['href']) ?>"><?= e($section['label']) ?><?= icon('arrow-right', 'size-4 text-rust-deep') ?></a>
        <?php if (!empty($section['children'])): ?>
          <ul class="mt-3 space-y-1.5 pl-5" role="list">
            <?php foreach ($section['children'] as $child): ?>
              <li><a class="text-ink-soft underline decoration-ink-soft/35 underline-offset-4 hover:text-ink hover:decoration-ink" href="<?= e($child['href']) ?>"><?= e($child['label']) ?></a></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      </li>
    <?php endforeach ?>
  </ol>
</nav>
