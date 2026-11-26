<?php
declare(strict_types=1);

/**
 * «Иная информация»: по решению лагеря. Текст и файлы из админки, ссылки на политику и правила.
 * @var string $title
 * @var string $description
 * @var list<array{label:string,href?:string}> $crumbs
 * @var list<array{id:string,label:string}> $toc
 * @var string|null $updated
 * @var string $updatedText
 * @var string $other    richtext из админки
 * @var string $docs     список файлов (components/doc-list)
 */
use function Groza\View\t;

$this->layout('pages/svedeniya/_frame', compact('title', 'description', 'crumbs', 'toc', 'updated', 'updatedText'));
?>
<?php if ($other !== ''): ?>
  <div class="richtext max-w-[48rem] leading-relaxed text-ink"><?= $other ?></div>
<?php endif ?>
<?php if ($docs !== ''): ?>
  <section class="<?= $other !== '' ? 'mt-12' : '' ?>" aria-labelledby="inye-dokumenty">
    <h2 class="mb-5 font-display text-[clamp(1.5rem,1.2rem+1.1vw,2.125rem)] font-bold uppercase leading-[1.1] tracking-[0.015em] text-ink" id="inye-dokumenty">Документы</h2>
    <?= $docs ?>
  </section>
<?php endif ?>
<section class="<?= $other !== '' || $docs !== '' ? 'mt-12' : '' ?>" aria-labelledby="inye-ssylki">
  <h2 class="mb-5 font-display text-[clamp(1.5rem,1.2rem+1.1vw,2.125rem)] font-bold uppercase leading-[1.1] tracking-[0.015em] text-ink" id="inye-ssylki"><?= t('Политика и правила') ?></h2>
  <ul class="richtext" role="list">
    <li><a class="tap" href="/policy/">Политика обработки персональных данных</a></li>
    <li><a class="tap" href="/pravila/"><?= t('Правила пребывания в лагере') ?></a></li>
  </ul>
</section>
