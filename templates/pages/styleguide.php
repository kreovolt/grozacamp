<?php
declare(strict_types=1);

/**
 * Витрина дизайн-системы для разработчика (/_styleguide/, только env dev и test, noindex).
 * Показывает компоненты templates/components/* в реальном макете. Посетителям не видна.
 */
use function Groza\View\e;
use function Groza\View\pending;

$this->layout('layout', ['page' => 'styleguide', 'title' => 'Дизайн-система', 'noindex' => true]);

$photo = static fn (string $label, string $tone): string =>
    '<div class="flex h-full w-full items-end p-3 ' . $tone . '"><span class="font-display text-xs uppercase tracking-[0.16em] text-paper/80">' . e($label) . '</span></div>';
$swatches = [
    ['sand', '#f1ebdd', 'bg-sand', 'фон страниц'],
    ['forest', '#22392c', 'bg-forest', 'шапка, подвал, тёмные секции'],
    ['moss', '#5e7a4b', 'bg-moss', 'вторичные элементы, пунктир'],
    ['rust', '#c8562f', 'bg-rust', 'акценты, крупный текст'],
    ['rust-dark', '#b54a26', 'bg-rust-dark', 'кнопки с белым текстом'],
    ['gold', '#d9a93a', 'bg-gold', 'цифры, кикеры на тёмном'],
    ['ink', '#1f2a22', 'bg-ink', 'основной текст'],
    ['paper', '#ffffff', 'bg-paper', 'карточки, рамки фото'],
];
?>
<section class="on-dark relative overflow-hidden bg-forest text-cream">
  <?= $this->partial('components/topo') ?>
  <div class="wrap relative py-16 desk:py-24">
    <?= $this->partial('components/section-head', [
        'kicker' => 'Смены лета · Пермский край',
        'title' => 'Лагерь настоящих приключений',
        'lead' => 'Походы, сплавы, спорт и командные игры под присмотром опытных инструкторов.',
        'tone' => 'dark',
        'level' => 1,
    ]) ?>
    <div class="mt-8 flex flex-wrap gap-3">
      <?= $this->partial('components/button', ['label' => 'Выбрать смену', 'href' => '#']) ?>
      <?= $this->partial('components/button', ['label' => 'Информация для родителей', 'href' => '#', 'variant' => 'outline-light']) ?>
    </div>
  </div>
</section>

<?= $this->partial('components/stats', ['items' => [
    '2016|работаем с этого года',
    '40+|проведённых смен',
    'до 24|детей в отряде, 2 вожатых',
    '5 раз|питание в день',
]]) ?>

<section class="wrap grid grid-cols-1 items-center gap-12 py-16 lg:grid-cols-[1fr_1.3fr] desk:py-24">
  <?= $this->partial('components/section-head', [
      'kicker' => 'Маршрут смены',
      'title' => 'Как проходит смена',
      'lead' => 'Заезд и знакомство, программа смены, большой финал — и фото на память.',
  ]) ?>
  <div class="grid grid-cols-3 gap-3 sm:gap-4">
    <?= $this->partial('components/polaroid', ['media' => $photo('фото 1', 'bg-moss'), 'tilt' => 'left', 'caption' => 'Вечер у костра']) ?>
    <?= $this->partial('components/polaroid', ['media' => $photo('фото 2', 'bg-forest'), 'tilt' => 'right', 'class' => 'translate-y-4', 'caption' => 'Наши домики']) ?>
    <?= $this->partial('components/polaroid', ['media' => $photo('фото 3', 'bg-rust'), 'tilt' => 'slight', 'caption' => 'Вожатые']) ?>
  </div>
</section>

<section class="border-t border-sand-deep bg-paper">
  <div class="wrap grid grid-cols-1 gap-12 py-16 lg:grid-cols-2">
    <div>
      <h2 class="font-display text-2xl font-bold uppercase">Кнопки</h2>
      <div class="mt-6 flex flex-wrap items-center gap-3">
        <?= $this->partial('components/button', ['label' => 'Купить путёвку', 'href' => '#', 'size' => 'sm']) ?>
        <?= $this->partial('components/button', ['label' => 'Забронировать', 'href' => '#']) ?>
        <?= $this->partial('components/button', ['label' => 'Отправить заявку', 'size' => 'lg', 'type' => 'submit']) ?>
        <?= $this->partial('components/button', ['label' => 'Подробнее', 'href' => '#', 'variant' => 'outline']) ?>
        <?= $this->partial('components/button', ['label' => 'Позвонить', 'href' => '#', 'variant' => 'outline', 'icon' => 'phone']) ?>
      </div>
      <h2 class="mt-12 font-display text-2xl font-bold uppercase">Текст</h2>
      <p class="mt-4 max-w-[62ch]">Основной текст — Manrope 16 px, межстрочный 1,6. Обязательное поле без данных выглядит так: <?= pending('') ?>.</p>
      <?= $this->partial('partials/breadcrumbs', ['class' => 'mt-6', 'items' => [
          ['label' => 'Главная', 'href' => '/'],
          ['label' => 'Сведения об организации отдыха детей и их оздоровления', 'href' => '/svedeniya/'],
          ['label' => 'Доступная среда'],
      ]]) ?>
    </div>
    <div>
      <h2 class="font-display text-2xl font-bold uppercase">Цвета</h2>
      <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4" role="list">
        <?php foreach ($swatches as [$name, $hex, $bg, $use]): ?>
          <li>
            <span class="block h-14 rounded-card border border-ink/10 <?= $bg ?>"></span>
            <span class="mt-2 block text-sm font-semibold"><?= e($name) ?> <span class="font-normal text-ink-soft"><?= e($hex) ?></span></span>
            <span class="block text-[0.8125rem] leading-snug text-ink-soft"><?= e($use) ?></span>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
</section>
