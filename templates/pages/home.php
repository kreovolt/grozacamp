<?php
declare(strict_types=1);

/**
 * Главная (спека §7.1), двенадцать блоков по порядку; у каждого data-block (порядок проверяют тесты).
 * Тексты героя, цифры, «что входит» и распорядок — из texts; тексты блоков 4–7 и 12 — здесь, в шаблоне
 * (их меняет разработчик; только факты из FAQ, текстов и спеки).
 * @var array{kicker:string, title:string, lead:string, photo:string} $hero
 * @var list<string> $stats
 * @var list<array<string,mixed>> $shifts
 * @var array<string,array<string,mixed>> $venues
 * @var list<array{venue:array<string,mixed>, shifts:list<string>}> $bases
 * @var list<string> $included
 * @var list<array{0:string,1:string}> $schedule
 * @var list<array<string,mixed>> $team
 * @var list<array<string,mixed>> $faq
 * @var list<array<string,mixed>> $reviews
 * @var string $reviewsUrl
 * @var array<string,mixed> $settings
 */
use Groza\Pages\HomePage;
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\image;
use function Groza\View\photo;
use function Groza\View\picture;
use function Groza\View\tel;

$this->layout('layout', [
    'description' => 'Детский лагерь «Гроза» в Пермском крае: походы, спорт, командные игры и вечера у костра. '
        . 'Отряды до 24 детей, с каждым — двое вожатых. Трансфер из Перми и питание пять раз в день входят в стоимость.',
]);

$section = 'py-16 sm:py-20 desk:py-24';
$shot = static fn (string $key, string $alt, string $sizes): string => picture(photo($key), $alt, $sizes, false, 'h-full w-full object-cover');
// Ссылка «ещё»: стрелка не отрывается от последнего слова при переносе
$moreLink = 'group/more inline-block font-display text-[0.9375rem] font-semibold uppercase leading-snug tracking-btn text-rust-deep no-underline hover:text-rust-dark';
$moreArrow = "\u{00A0}" . icon('arrow-right', 'inline-block size-4 align-[-0.2em] transition-transform group-hover/more:translate-x-0.5');

$heroImg = image($hero['photo']);
// Положение кадра из манифеста фото → класс (без inline-style: CSP)
$heroPosition = ['center 40%' => 'object-[center_40%]', 'center top' => 'object-top'][$heroImg['position'] ?? ''] ?? 'object-center';

// Блок 4 — для ребят, на «ты». Только то, что есть в программах смен, распорядке и FAQ.
$kids = [
    ['Походы и ориентирование', 'Научишься читать карту, ориентироваться на местности и ставить лагерь на природе.'],
    ['Лазертаг', 'Командная игра на местности: выигрывает не самый быстрый, а самый сыгранный отряд.'],
    ['Первая помощь', 'Разберёшься, что делать, если кто-то поранился. Это пригодится не только в лагере.'],
    ['Сцена и творчество', 'Выступления на сцене, мастер-классы, актёрское мастерство и дискотеки.'],
    ['Вечерний костёр', 'Вечера у костра, а перед сном — «свечка», вечерний сбор отряда.'],
    ['Новые друзья', 'В отряде — ребята твоего возраста, а вожатые — старшие товарищи, которые рядом весь день.'],
];

// Блок 5 — для родителей. Факты из FAQ и спеки §7.1 п. 5.
$vk = (string) ($settings['vk'] ?? '');
$care = [
    ['До 24 детей в отряде', e('С отрядом работают двое вожатых, а у младших отрядов есть ещё помощники.')],
    ['Медик на смене', e('На смене работает дежурный медик, это входит в стоимость путёвки.')],
    ['Связь каждый день', e('Телефон у ребёнка в тихий час и после вечерних мероприятий: позвонить домой можно каждый день.')],
    ['Фото и видео каждый день', e('Пресс-центр лагеря выкладывает их в ')
        . ($vk !== '' ? '<a class="font-semibold text-cream underline decoration-gold/60 underline-offset-4 hover:decoration-gold" href="' . e($vk) . '" rel="noopener" target="_blank">группе ВКонтакте</a>' : e('группе ВКонтакте'))
        . e(', так что видно, чем ребёнок занят.')],
    ['Проверенная территория', e('База проходит проверку Роспотребнадзора, территорию регулярно обрабатывают от клещей.')],
    ['Тир под присмотром', e('Пневматика — только в огороженном тире. Занятие ведёт спортсмен-стрелок, с ним помощник на каждые два рубежа.')],
];

// Блок 6 — маршрут смены
$steps = [
    ['title' => 'Заезд и знакомство', 'text' => 'Встречаемся в Перми и вместе едем в лагерь на автобусе. Ребята заселяются и знакомятся с отрядом и вожатыми.'],
    ['title' => 'Программа смены', 'text' => 'Каждый день занятия по программе и мастер-классы, вечером — сцена, дискотеки и костёр.'],
    ['title' => 'Большой финал', 'text' => 'В конце смены — итоговое задание всем отрядом. А потом автобус привозит ребят обратно в Пермь.'],
];
?>
<?php /* 1. Герой */ ?>
<section class="on-dark relative isolate flex min-h-[calc(100svh-4rem)] flex-col justify-end overflow-hidden bg-forest text-cream lg:min-h-[clamp(36rem,calc(100svh-16rem),46rem)]" data-block="hero" aria-labelledby="hero-title">
  <div class="absolute inset-x-0 top-0 -z-10 h-[60%] lg:inset-0 lg:h-full">
    <?= picture($heroImg, 'Ребята на берегу реки брызгаются водой и смеются', '100vw', true, 'h-full w-full object-cover ' . $heroPosition) ?>
    <div class="hero-shade absolute inset-0"></div>
  </div>
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.2]']) ?>

  <div class="wrap pb-10 pt-48 sm:pb-14 lg:pb-20 lg:pt-28">
    <div class="max-w-[44rem]">
      <p class="kicker !leading-[1.5] text-gold"><?= e($hero['kicker']) ?></p>
      <h1 class="mt-4 font-display text-[clamp(2.5rem,1.3rem+4.8vw,4.75rem)] font-bold uppercase leading-[1.1] tracking-[0.01em] text-[#f4efe3]" id="hero-title"><?= e($hero['title']) ?></h1>
      <?php if ($hero['lead'] !== ''): ?>
        <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= e($hero['lead']) ?></p>
      <?php endif ?>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <?= $this->partial('components/button', ['label' => 'Выбрать смену', 'href' => '/booking/', 'size' => 'lg']) ?>
        <?= $this->partial('components/button', ['label' => 'Информация для родителей', 'href' => '/info/', 'size' => 'lg', 'variant' => 'outline-light']) ?>
      </div>
    </div>
  </div>
</section>

<?php /* 2. Полоса цифр */ ?>
<div data-block="stats">
  <?= $this->partial('components/stats', ['items' => $stats]) ?>
</div>

<?php /* 3. Смены сезона */ ?>
<section class="<?= $section ?>" data-block="shifts" aria-labelledby="shifts-title">
  <div class="wrap">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <?= $this->partial('components/section-head', [
          'title' => 'Выберите смену',
          'lead' => 'Даты, возраст и цена — на карточке. Проживание, питание и трансфер из Перми уже входят в стоимость.',
          'id' => 'shifts-title',
      ]) ?>
      <?php if ($shifts !== []): ?>
        <a class="<?= $moreLink ?> shrink-0 lg:mb-1.5" href="/booking/">Все смены и бронирование<?= $moreArrow ?></a>
      <?php endif ?>
    </div>

    <?php if ($shifts !== []): ?>
      <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3 desk:gap-8">
        <?php foreach ($shifts as $shift): ?>
          <?= $this->partial('components/shift-card', [
              'shift' => $shift,
              'venue' => $venues[(string) ($shift['venue'] ?? '')] ?? null,
              'bookingHref' => HomePage::bookingHref($shift),
          ]) ?>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="mt-10 flex flex-col items-start gap-5 rounded-card border-2 border-dashed border-moss/45 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <p class="max-w-[48ch] text-lg font-semibold leading-snug text-ink">Даты новых смен скоро появятся. Оставьте заявку — позвоним первыми.</p>
        <?= $this->partial('components/button', ['label' => 'Оставить заявку', 'href' => '/booking/', 'class' => 'shrink-0']) ?>
      </div>
    <?php endif ?>
  </div>
</section>

<?php /* 4. Что тебя ждёт — для ребят */ ?>
<section class="overflow-hidden bg-sand-deep <?= $section ?>" data-block="kids" aria-labelledby="kids-title">
  <div class="wrap grid items-center gap-14 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-14 desk:gap-20">
    <div class="lg:order-2">
      <?= $this->partial('components/section-head', [
          'title' => 'Что тебя ждёт',
          'lead' => 'Днём — занятия на природе, вечером — сцена, дискотеки и костёр. И отряд, который к концу смены станет твоей командой.',
          'id' => 'kids-title',
      ]) ?>
      <ul class="mt-10 grid gap-x-10 sm:grid-cols-2" role="list">
        <?php foreach ($kids as [$title, $text]): ?>
          <li class="border-t border-dashed border-moss/50 py-5">
            <h3 class="font-display text-[1.1875rem] font-semibold uppercase leading-tight tracking-[0.04em] text-ink"><?= e($title) ?></h3>
            <p class="mt-1.5 text-[0.9375rem] leading-relaxed text-ink-soft"><?= e($text) ?></p>
          </li>
        <?php endforeach ?>
      </ul>
    </div>

    <div class="relative mx-auto flex w-full max-w-xl items-start lg:order-1 lg:block lg:h-[38rem] lg:max-w-none">
      <?= $this->partial('components/polaroid', [
          'media' => $shot('kids-girls', 'Девочки из отряда обнимаются и смеются на вечерней дискотеке', '(min-width: 1024px) 28rem, 60vw'),
          'ratio' => '4/3',
          'tilt' => 'left',
          'caption' => 'Своя компания',
          'class' => 'relative z-10 w-[62%] shrink-0 lg:absolute lg:left-0 lg:top-0 lg:w-[64%]',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('spas-laugh', 'Ребята с аквагримом смеются, сидя рядом', '(min-width: 1024px) 24rem, 55vw'),
          'ratio' => '4/3',
          'tilt' => 'right',
          'caption' => 'Смеёмся всем отрядом',
          'class' => 'relative z-20 -ml-[14%] mt-16 w-[52%] shrink-0 lg:absolute lg:right-0 lg:top-[27%] lg:ml-0 lg:mt-0 lg:w-[54%]',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('kids-bubbles', 'Девочка пускает мыльные пузыри на солнечной поляне', '14rem'),
          'ratio' => '3/4',
          'tilt' => 'slight',
          'caption' => 'Лето за городом',
          'class' => 'hidden lg:absolute lg:bottom-0 lg:left-[12%] lg:z-30 lg:block lg:w-[29%]',
      ]) ?>
    </div>
  </div>
</section>

<?php /* 5. Спокойно за ребёнка — для родителей */ ?>
<section class="on-dark relative overflow-hidden bg-forest text-cream <?= $section ?>" data-block="care" aria-labelledby="care-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 h-full w-full text-sand opacity-[0.08]']) ?>
  <div class="wrap relative grid gap-12 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16 desk:gap-24">
    <div>
      <?= $this->partial('components/section-head', [
          'title' => 'Спокойно за ребёнка',
          'lead' => 'Вы доверяете нам самое ценное. Вот как устроена смена, чтобы вам было спокойно, пока ребёнок в лагере.',
          'tone' => 'dark',
          'id' => 'care-title',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('counselors-kids', 'Трое вожатых в форме лагеря улыбаются на берегу реки', '(min-width: 1024px) 26rem, 80vw'),
          'ratio' => '4/3',
          'tilt' => 'left',
          'caption' => 'Вожатые — старшие товарищи для ребят',
          'class' => 'mt-10 max-w-[26rem] lg:mt-12',
      ]) ?>
    </div>

    <div class="lg:pt-2">
      <ul class="grid gap-x-10 sm:grid-cols-2" role="list">
        <?php foreach ($care as [$title, $html]): ?>
          <li class="border-t border-dashed border-sage/35 py-6">
            <h3 class="font-display text-xl font-semibold uppercase leading-tight tracking-[0.03em] text-gold"><?= e($title) ?></h3>
            <p class="mt-2 leading-relaxed text-cream/85"><?= $html ?></p>
          </li>
        <?php endforeach ?>
      </ul>
      <a class="group/more mt-6 inline-block font-display text-[0.9375rem] font-semibold uppercase leading-snug tracking-btn text-gold no-underline hover:text-cream" href="/info/">Памятка для родителей<?= $moreArrow ?></a>
    </div>
  </div>
</section>

<?php /* 6. Как проходит смена + распорядок дня */ ?>
<section class="<?= $section ?>" data-block="steps" aria-labelledby="steps-title">
  <div class="wrap">
    <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] lg:gap-16">
      <div>
        <?= $this->partial('components/section-head', ['title' => 'Как проходит смена', 'id' => 'steps-title']) ?>
        <?= $this->partial('components/steps', ['steps' => $steps, 'class' => 'mt-10']) ?>
      </div>
      <div class="grid grid-cols-3 items-start gap-3 sm:gap-5">
        <?= $this->partial('components/polaroid', [
            'media' => $shot('kids-gazebo', 'Ребята из отряда сидят вместе в беседке', '(min-width: 1024px) 14rem, 31vw'),
            'tilt' => 'left',
            'caption' => 'Знакомимся',
        ]) ?>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('spas-demo', 'Спасатель МЧС показывает ребятам, как переносить пострадавшего', '(min-width: 1024px) 14rem, 31vw'),
            'tilt' => 'right',
            'caption' => 'Первая помощь',
            'class' => 'translate-y-6',
        ]) ?>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('sturm-cheer', 'Отряд радуется, подняв руки вверх', '(min-width: 1024px) 14rem, 31vw'),
            'tilt' => 'slight',
            'caption' => 'Всем отрядом',
        ]) ?>
      </div>
    </div>

    <?php if ($schedule !== []): ?>
      <details class="group mt-16 rounded-card bg-paper shadow-card lg:mt-20">
        <summary class="flex items-center justify-between gap-6 rounded-card px-6 py-5 transition-colors hover:bg-paper/60 sm:px-8 sm:py-6">
          <span>
            <span class="block font-display text-[1.375rem] font-semibold uppercase leading-tight tracking-[0.03em] text-ink">Распорядок дня</span>
            <span class="mt-1 block text-[0.9375rem] text-ink-soft">Как устроен обычный день на смене — от подъёма до отбоя</span>
          </span>
          <span class="grid size-11 shrink-0 place-items-center rounded-full border-2 border-dashed border-moss text-forest transition-transform duration-200 group-open:rotate-180" aria-hidden="true">
            <?= icon('chevron-down', 'size-5') ?>
          </span>
        </summary>
        <div class="border-t border-dashed border-moss/45 px-6 pb-7 pt-4 sm:px-8">
          <dl class="sm:columns-2 sm:gap-14">
            <?php foreach ($schedule as [$time, $what]): ?>
              <div class="flex break-inside-avoid gap-5 border-b border-dashed border-sand-deep py-3">
                <dt class="tabular w-[7.5rem] shrink-0 font-display text-[1.0625rem] font-medium tracking-[0.02em] text-forest"><?= e($time) ?></dt>
                <dd class="text-ink"><?= e($what) ?></dd>
              </div>
            <?php endforeach ?>
          </dl>
        </div>
      </details>
    <?php endif ?>
  </div>
</section>

<?php /* 7. Что входит в стоимость */ ?>
<section class="bg-sand-deep [--notch:theme(colors.sand.deep)] <?= $section ?>" data-block="included" aria-labelledby="included-title">
  <div class="wrap">
    <?= $this->partial('components/section-head', ['title' => 'Что входит в стоимость', 'id' => 'included-title']) ?>
    <?= $this->partial('components/included', ['items' => $included, 'ctaHref' => '/booking/', 'class' => 'mt-10']) ?>
  </div>
</section>

<?php /* 8. Где живём */ ?>
<?php if ($bases !== []): ?>
  <section class="<?= $section ?>" data-block="base" aria-labelledby="base-title">
    <div class="wrap">
      <?= $this->partial('components/section-head', [
          'title' => 'Где живём',
          'lead' => 'Здесь пройдут ближайшие смены. Посмотрите, где будут жить ребята.',
          'id' => 'base-title',
      ]) ?>
      <?php foreach ($bases as $base): ?>
        <?php
        $venue = $base['venue'];
        $photos = [];
        foreach (array_slice((array) ($venue['photos'] ?? []), 0, 5) as $value) {
            try {
                $img = image(is_array($value) || is_string($value) ? $value : null);
            } catch (\InvalidArgumentException) {
                $img = null;
            }
            if ($img !== null) {
                $photos[] = $img;
            }
        }
        $titles = implode(', ', array_map(static fn (string $t): string => '«' . $t . '»', array_unique($base['shifts'])));
        ?>
        <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)] lg:gap-12">
          <div class="lg:pt-2">
            <h3 class="font-display text-[1.75rem] font-bold uppercase leading-tight tracking-[0.02em] text-ink"><?= e((string) $venue['name']) ?></h3>
            <?php if (!empty($venue['address'])): ?>
              <p class="mt-3 flex items-start gap-2 leading-snug text-ink-soft"><?= icon('map-pin', 'mt-0.5 size-[1.125rem] text-moss') ?><span><?= e((string) $venue['address']) ?></span></p>
            <?php endif ?>
            <p class="mt-5 border-t border-dashed border-moss/45 pt-5 leading-relaxed text-ink">
              <?= count(array_unique($base['shifts'])) > 1 ? 'Здесь пройдут смены' : 'Здесь пройдёт смена' ?> <?= e($titles) ?>.
            </p>
            <a class="<?= $moreLink ?> mt-6" href="/svedeniya/mto/">Условия проживания<?= $moreArrow ?></a>
          </div>
          <?php if ($photos !== []): ?>
            <ul class="grid auto-rows-[8.5rem] grid-cols-2 gap-3 sm:auto-rows-[11rem] sm:grid-cols-4 sm:gap-4" role="list">
              <?php foreach ($photos as $i => $img): ?>
                <li class="<?= $i === 0 ? 'col-span-2 row-span-2 ' : '' ?>overflow-hidden rounded-card bg-sand-deep">
                  <?= picture($img, (string) ($img['alt'] ?? $venue['name'] . ': фото ' . ($i + 1) . ' из ' . count($photos)), $i === 0 ? '(min-width: 1024px) 32rem, 100vw' : '(min-width: 1024px) 16rem, 50vw', false, 'h-full w-full object-cover') ?>
                </li>
              <?php endforeach ?>
            </ul>
          <?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  </section>
<?php endif ?>

<?php /* 9. Команда */ ?>
<?php if ($team !== []): ?>
  <section class="bg-paper <?= $section ?>" data-block="team" aria-labelledby="team-title">
    <div class="wrap">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <?= $this->partial('components/section-head', [
            'title' => 'Кто будет рядом',
            'lead' => 'Вожатые, педагоги и инструкторы «Грозы» — команда, которая проводит смены.',
            'id' => 'team-title',
        ]) ?>
        <a class="<?= $moreLink ?> shrink-0 lg:mb-1.5" href="/team/">Вся команда<?= $moreArrow ?></a>
      </div>
      <?= $this->partial('components/team-strip', ['members' => $team, 'class' => 'mt-10']) ?>
    </div>
  </section>
<?php endif ?>

<?php /* 10. Отзывы родителей */ ?>
<?php if ($reviews !== [] || $reviewsUrl !== ''): ?>
  <section class="<?= $section ?>" data-block="reviews" aria-labelledby="reviews-title">
    <div class="wrap">
      <?= $this->partial('components/section-head', ['title' => 'Что говорят родители', 'id' => 'reviews-title']) ?>
      <?php if ($reviews !== []): ?>
        <div class="mt-10 grid items-start gap-x-14 gap-y-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
          <?= $this->partial('components/review', ['review' => $reviews[0], 'featured' => true]) ?>
          <?php if (count($reviews) > 1): ?>
            <div class="divide-y divide-dashed divide-moss/45 border-y border-dashed border-moss/45">
              <?php foreach (array_slice($reviews, 1) as $review): ?>
                <?= $this->partial('components/review', ['review' => $review]) ?>
              <?php endforeach ?>
            </div>
          <?php endif ?>
        </div>
        <?php if ($reviewsUrl !== ''): ?>
          <a class="<?= $moreLink ?> mt-8" href="<?= e($reviewsUrl) ?>" rel="noopener" target="_blank">Ещё отзывы во ВКонтакте<?= $moreArrow ?></a>
        <?php endif ?>
      <?php else: ?>
        <div class="mt-8 flex flex-col items-start gap-5 rounded-card border-2 border-dashed border-moss/45 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
          <p class="max-w-[52ch] text-lg leading-snug text-ink">Родители рассказывают о сменах в обсуждении в нашей группе ВКонтакте. Почитайте, как ребятам у нас.</p>
          <?= $this->partial('components/button', [
              'label' => 'Читать отзывы',
              'href' => $reviewsUrl,
              'variant' => 'outline',
              'icon' => 'vk',
              'class' => 'shrink-0',
              'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
          ]) ?>
        </div>
      <?php endif ?>
    </div>
  </section>
<?php endif ?>

<?php /* 11. Частые вопросы */ ?>
<?php if ($faq !== []): ?>
  <section class="bg-paper <?= $section ?>" data-block="faq" aria-labelledby="faq-title">
    <div class="wrap grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
      <div>
        <?= $this->partial('components/section-head', [
            'title' => 'Частые вопросы',
            'lead' => 'Коротко о главном. Остальные ответы — на странице для родителей.',
            'id' => 'faq-title',
        ]) ?>
        <a class="<?= $moreLink ?> mt-6" href="/info/#faq">Все вопросы и ответы<?= $moreArrow ?></a>
        <?php if (!empty($settings['phone'])): ?>
          <p class="mt-8 max-w-[30ch] leading-relaxed text-ink-soft">
            Не нашли ответа? Позвоните:
            <a class="tabular whitespace-nowrap font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="<?= e(tel((string) $settings['phone'])) ?>"><?= e(Phone::pretty((string) $settings['phone'])) ?></a>
          </p>
        <?php endif ?>
      </div>
      <?= $this->partial('components/faq-list', ['items' => $faq]) ?>
    </div>
  </section>
<?php endif ?>

<?php /* 12. Финальный призыв */ ?>
<section data-block="cta" aria-labelledby="cta-title">
  <?= $this->partial('components/cta-final', [
      'settings' => $settings,
      'title' => 'Готовы к приключению?',
      'lead' => 'Выберите смену и оставьте заявку. Офис перезвонит, ответит на вопросы и поможет оформить путёвку.',
      'href' => '/booking/',
      'image' => photo('kids-evening'),
      'id' => 'cta-title',
  ]) ?>
</section>
