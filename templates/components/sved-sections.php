<?php
declare(strict_types=1);

/**
 * Разделы страницы сведений: заголовок и пункты приказа списком определений
 * (термин — дословная формулировка пункта, значение — данные или «Информация обновляется»).
 * Значения в $rows[*]['value'] — готовый безопасный HTML (его собирает SvedeniyaPage).
 * @var list<array{id:string,title:string,lead?:string,rows:list<array{item:string,paragraph:string,full:string,value:string,wide:bool}>}> $sections
 */
use function Groza\View\e;

foreach ($sections as $i => $s):
    ?>
<section class="scroll-mt-6 <?= $i > 0 ? 'mt-14 sm:mt-16' : '' ?>" id="<?= e($s['id']) ?>" aria-labelledby="<?= e($s['id']) ?>-title">
  <h2 class="font-display text-[clamp(1.5rem,1.2rem+1.1vw,2.125rem)] font-bold uppercase leading-[1.1] tracking-[0.015em] text-ink" id="<?= e($s['id']) ?>-title"><?= e($s['title']) ?></h2>
  <?php if (($s['lead'] ?? '') !== ''): ?>
    <p class="mt-2 text-ink-soft"><?= e($s['lead']) ?></p>
  <?php endif ?>
  <dl class="mt-5 border-t border-dashed border-moss/45">
    <?php foreach ($s['rows'] as $r): ?>
      <div class="grid gap-x-10 gap-y-2 border-b border-dashed border-moss/45 py-5 <?= $r['wide'] ? '' : 'md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]' ?>" data-item="<?= e($r['item']) ?>">
        <dt class="text-[0.9375rem] leading-relaxed text-ink-soft"><span class="sved-par mr-1.5 whitespace-nowrap font-display text-[0.875rem] font-semibold tracking-[0.08em] text-rust-deep">п. <?= e($r['paragraph']) ?></span><?= e($r['full']) ?></dt>
        <dd class="richtext min-w-0 leading-relaxed text-ink"><?= $r['value'] ?></dd>
      </div>
    <?php endforeach ?>
  </dl>
</section>
<?php endforeach ?>
