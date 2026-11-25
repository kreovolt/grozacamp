<?php
declare(strict_types=1);

/**
 * «Что входит в стоимость» в виде путёвки: слева — список (texts home.included), справа за линией отрыва —
 * корешок про сертификаты и компенсацию и кнопка. Вырезы на линии отрыва — цвета фона секции:
 * по умолчанию bg-sand, на другом фоне задайте его секции, например [--notch:theme(colors.sand.deep)].
 * @var list<string> $items
 * @var string|null $ctaHref    кнопка «Выбрать смену» на корешке; нет — без кнопки
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\icon;

if ($items === []) {
    return;
}
?>
<div class="<?= e(trim('grid overflow-hidden rounded-card bg-paper shadow-card lg:grid-cols-[minmax(0,1fr)_22rem] ' . ($class ?? ''))) ?>">
  <ul class="grid content-center gap-x-10 gap-y-6 p-6 sm:grid-cols-2 sm:p-10" role="list">
    <?php foreach ($items as $item): ?>
      <li class="flex gap-3.5 leading-snug">
        <?= icon('check', 'mt-0.5 size-5 text-moss') ?>
        <span class="text-[1.0625rem] text-ink"><?= e($item) ?></span>
      </li>
    <?php endforeach ?>
  </ul>

  <div class="ticket-stub relative flex flex-col items-start gap-4 bg-sand/50 p-6 sm:p-10">
    <p class="font-display text-xl font-semibold uppercase leading-tight tracking-[0.03em] text-ink">Сертификаты и компенсация</p>
    <p class="leading-relaxed text-ink-soft">Принимаем сертификаты и даём документы для компенсации стоимости путёвки.</p>
    <a class="inline-flex items-center gap-1.5 font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="/svedeniya/uslugi/">
      Подробнее об оплате <?= icon('arrow-right', 'size-4') ?>
    </a>
    <?php if (!empty($ctaHref)): ?>
      <?= $this->partial('components/button', ['label' => 'Выбрать смену', 'href' => $ctaHref, 'class' => 'mt-2']) ?>
    <?php endif ?>
  </div>
</div>
