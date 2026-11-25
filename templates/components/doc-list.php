<?php
declare(strict_types=1);

/**
 * Файлы документов списком: название-ссылка, тип и размер («PDF, 1,2 МБ»), дата документа.
 * @var list<array{title:string,url:string,meta:string,date:string}> $docs
 */
use function Groza\View\e;
use function Groza\View\icon;

if (($docs ?? []) === []) {
    return;
}
?>
<ul class="doc-list not-prose space-y-2.5" role="list">
  <?php foreach ($docs as $doc): ?>
    <li class="!ml-0 flex items-start gap-2.5">
      <?= icon('arrow-right', 'mt-1 size-4 text-rust-deep') ?>
      <span><a class="font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="<?= e($doc['url']) ?>" target="_blank" rel="noopener"><?= e($doc['title']) ?></a>
        <span class="text-ink-soft">(<?= e($doc['meta']) ?>)</span><?php if ($doc['date'] !== ''): ?><span class="text-ink-soft">, от <?= e($doc['date']) ?></span><?php endif ?></span>
    </li>
  <?php endforeach ?>
</ul>
