<?php
declare(strict_types=1);

/**
 * Главная (спека §7.1), двенадцать блоков по порядку; у каждого data-block (порядок проверяют тесты).
 * Тексты героя, цифры, «что входит» и распорядок — из texts; тексты блоков 4–7 и 12 — здесь, в шаблоне
 * (их меняет разработчик; только факты из FAQ, текстов и спеки).
 * @var array{kicker:string, title:string, lead:string, photo:string|array<string,mixed>} $hero  photo — ключ библиотеки или загруженное фото
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
 * @var bool $reviewsIsThread  ссылка ведёт на обсуждение с отзывами (а не на группу)
 * @var array<string,mixed> $settings
 */
use Groza\Pages\HomePage;
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\image;
use function Groza\View\photo;
use function Groza\View\picture;
use function Groza\View\safe_url;
use function Groza\View\t;
use function Groza\View\tel;
use function Groza\View\typeset;

$this->layout('layout', ['page' => 'home']);

$section = 'py-16 sm:py-20 desk:py-24';
// Фото из библиотеки: подпись alt — описание снимка из config/photos.json (один источник для всех страниц)
$shot = static function (string $key, string $sizes): string {
    $img = photo($key);

    return picture($img, (string) ($img['alt'] ?? ''), $sizes, false, 'h-full w-full object-cover');
};
// Ссылка «ещё»: стрелка не отрывается от последнего слова при переносе
$moreLink = 'group/more inline-block font-display text-[0.9375rem] font-semibold uppercase leading-snug tracking-btn text-rust-deep no-underline hover:text-rust-dark';
$moreArrow = "\u{00A0}" . icon('arrow-right', 'inline-block size-4 align-[-0.2em] transition-transform group-hover/more:translate-x-0.5');

$heroImg = image($hero['photo']);
// Положение кадра из манифеста фото → класс (без inline-style: CSP)
$heroPosition = ['60% 30%' => 'object-[60%_30%]', 'center 40%' => 'object-[center_40%]', 'center top' => 'object-top'][$heroImg['position'] ?? ''] ?? 'object-center';

// Блок 4 — для ребят, на «ты». Только то, что есть в программах смен на старом сайте (content/old-site-content.md),
// распорядке и FAQ; обобщения — в content/drafts-for-client.md, «Главная — подтвердить».
$kids = [
    ['Походы и ориентирование', 'Туристская подготовка, топография и ориентирование на местности. Научишься ставить лагерь.'],
    ['Лазертаг', 'Лазертаг и командные задания, где учишься действовать вместе с командой.'],
    ['Первая помощь', 'Научишься оказывать первую помощь и поймёшь, что делать, если кто-то поранился.'],
    ['Сцена и творчество', 'Выступления на сцене, мастер-классы, актёрское мастерство и дискотеки.'],
    ['Вечерний костёр', 'Вечера у костра, а перед сном — «свечка», вечерний сбор отряда.'],
    ['Новые друзья', 'Много новых друзей и своя программа для каждого возраста. А вожатые — старшие товарищи.'],
];

// Блок 5 — для родителей. Факты из FAQ и спеки §7.1 п. 5.
$vk = safe_url($settings['vk'] ?? '');
// Тексты — готовый HTML через типограф: t() для простого текста, typeset() — когда внутри ссылка (предлог перед ней не отрывается)
$care = [
    ['До 24 детей в отряде', t('С отрядом работают двое вожатых, а у младших отрядов есть ещё помощники.')],
    ['Медик на смене', t('На смене работает дежурный медик, это входит в стоимость путёвки.')],
    ['Связь каждый день', t('Телефон у ребёнка в тихий час и после вечерних мероприятий: позвонить домой можно каждый день.')],
    ['Фото и видео каждый день', typeset('Пресс-центр лагеря выкладывает их в '
        . ($vk !== '' ? '<a class="font-semibold text-cream underline decoration-gold/60 underline-offset-4 hover:decoration-gold" href="' . e($vk) . '" rel="noopener" target="_blank">группе ВКонтакте</a>' : 'группе ВКонтакте')
        . ', так что видно, чем ребёнок занят.')],
    ['Проверенная территория', t('База проходит проверку Роспотребнадзора, территорию регулярно обрабатывают от клещей.')],
    ['Тир под присмотром', t('Пневматика — только в огороженном тире. Занятие ведёт спортсмен-стрелок, с ним помощник на каждые два рубежа.')],
];

// Блок 6 — маршрут смены (старый сайт: выезд на автобусе из Перми, миссия «в завершающей части программы»)
$steps = [
    ['title' => 'Заезд и знакомство', 'text' => 'Встречаемся в Перми и организованно выезжаем в лагерь на автобусе. В первый день — расселение и знакомство с отрядом.'],
    ['title' => 'Программа смены', 'text' => 'Каждый день занятия по программе и мастер-классы, вечером — сцена, дискотеки и костёр.'],
    ['title' => 'Большой финал', 'text' => 'В завершающей части программы ребята вместе с инструкторами отправляются на итоговую миссию. После смены — трансфер обратно в Пермь.'],
];
?>
<?php /* 1. Герой: лесная панель с текстом и селфи в естественных цветах (с 1024 px — две колонки по сетке .wrap, на телефоне фото сверху) */ ?>
<section class="hero on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="hero-title">
  <?= $this->partial('components/topo', ['class' => 'hero-topo pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.16]']) ?>
  <div class="hero-grid wrap">
    <div class="hero-photo relative overflow-hidden bg-forest-deep">
      <?= picture($heroImg, (string) ($heroImg['alt'] ?? ''), '(min-width: 1360px) 660px, (min-width: 1024px) 52vw, 100vw', true, 'absolute inset-0 h-full w-full object-cover ' . $heroPosition) ?>
    </div>

    <div class="hero-copy">
      <div class="max-w-[34rem]">
        <p class="kicker !leading-[1.5] text-gold"><?= t($hero['kicker']) ?></p>
        <h1 class="mt-4 font-display text-[clamp(2.5rem,1.3rem+4.8vw,4rem)] font-bold uppercase leading-[1.08] tracking-[0.01em] text-[#f4efe3] lg:text-[clamp(3rem,0.25rem+4.4vw,4.25rem)]" id="hero-title" data-hero-title><?= t($hero['title']) ?></h1>
        <?php if ($hero['lead'] !== ''): ?>
          <p class="mt-5 max-w-[32rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= t($hero['lead']) ?></p>
        <?php endif ?>
        <?php /* С 1024 px колонка узкая: кнопки растягиваются — в один ряд на всю ширину или, если не помещаются, одна под другой одинаковой ширины */ ?>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap lg:max-w-[32rem] lg:[&>*]:grow">
          <?= $this->partial('components/button', ['label' => 'Выбрать смену', 'href' => '/booking/', 'size' => 'lg', 'class' => 'max-[359px]:px-5']) ?>
          <?= $this->partial('components/button', ['label' => 'Информация для родителей', 'href' => '/info/', 'size' => 'lg', 'variant' => 'outline-light', 'class' => 'max-[359px]:px-5']) ?>
        </div>
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
        <a class="<?= $moreLink ?> shrink-0 lg:mb-1.5" href="/booking/"><?= t('Все смены и бронирование') ?><?= $moreArrow ?></a>
      <?php endif ?>
    </div>

    <?php if ($shifts !== []): ?>
      <div class="shift-grid mt-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 desk:gap-8" data-reveal>
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
        <p class="max-w-[48ch] text-lg font-semibold leading-snug text-ink"><?= t('Даты новых смен скоро появятся. Оставьте заявку — позвоним первыми.') ?></p>
        <?= $this->partial('components/button', ['label' => 'Оставить заявку', 'href' => '/booking/', 'class' => 'shrink-0']) ?>
      </div>
    <?php endif ?>
  </div>
</section>

<?php /* 4. Что тебя ждёт — для ребят */ ?>
<section class="overflow-hidden bg-sand-deep <?= $section ?>" data-block="kids" aria-labelledby="kids-title">
  <div class="wrap grid grid-cols-1 items-center gap-14 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-14 desk:gap-20">
    <div class="lg:order-2">
      <?= $this->partial('components/section-head', [
          'title' => 'Что тебя ждёт',
          'lead' => 'Днём — занятия на природе, вечером — сцена, дискотеки и костёр. И отряд, который к концу смены станет твоей командой.',
          'id' => 'kids-title',
      ]) ?>
      <ul class="mt-10 grid grid-cols-1 gap-x-10 sm:grid-cols-2" role="list" data-reveal>
        <?php foreach ($kids as [$title, $text]): ?>
          <li class="border-t border-dashed border-moss/50 py-5">
            <h3 class="font-display text-[1.1875rem] font-semibold uppercase leading-tight tracking-[0.04em] text-ink"><?= t($title) ?></h3>
            <p class="mt-1.5 text-[0.9375rem] leading-relaxed text-ink-soft"><?= t($text) ?></p>
          </li>
        <?php endforeach ?>
      </ul>
    </div>

    <div data-reveal class="relative mx-auto flex w-full max-w-xl items-start lg:order-1 lg:block lg:h-[38rem] lg:max-w-none">
      <?= $this->partial('components/polaroid', [
          'media' => $shot('team-poster', '(min-width: 1024px) 28rem, 60vw'),
          'ratio' => '4/3',
          'tilt' => 'left',
          'caption' => 'Болеем за своих',
          'class' => 'relative z-10 w-[62%] shrink-0 lg:absolute lg:left-0 lg:top-0 lg:w-[64%]',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('stage-cheer', '(min-width: 1024px) 24rem, 55vw'),
          'ratio' => '4/3',
          'tilt' => 'right',
          'caption' => 'Номер удался!',
          'class' => 'relative z-20 -ml-[14%] mt-16 w-[52%] shrink-0 lg:absolute lg:right-0 lg:top-[27%] lg:ml-0 lg:mt-0 lg:w-[54%]',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('games-meadow', '14rem'),
          'ratio' => '3/4',
          'tilt' => 'slight',
          'caption' => 'Кто быстрее?',
          'class' => 'hidden lg:absolute lg:bottom-0 lg:left-[12%] lg:z-30 lg:block lg:w-[29%]',
      ]) ?>
    </div>
  </div>
</section>

<?php /* 5. Спокойно за ребёнка — для родителей */ ?>
<section class="on-dark relative overflow-hidden bg-forest text-cream <?= $section ?>" data-block="care" aria-labelledby="care-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 h-full w-full text-sand opacity-[0.08]']) ?>
  <div class="wrap relative grid grid-cols-1 gap-12 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16 desk:gap-24">
    <div>
      <?= $this->partial('components/section-head', [
          'title' => 'Спокойно за ребёнка',
          'lead' => 'Вы доверяете нам самое ценное. Вот как устроена смена, чтобы вам было спокойно, пока ребёнок в лагере.',
          'tone' => 'dark',
          'id' => 'care-title',
      ]) ?>
      <?php /* Вожатый рядом с ребятами — главный снимок блока; поверх второго, чтобы подпись не перекрывалась */ ?>
      <div class="mt-10 flex max-w-[30rem] items-start lg:mt-12" data-reveal>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('morning-exercise', '(min-width: 1024px) 18rem, 60vw'),
            'ratio' => '4/3',
            'tilt' => 'left',
            'caption' => 'Зарядка с вожатым',
            'class' => 'relative z-20 w-[60%] shrink-0',
        ]) ?>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('first-aid-field', '(min-width: 1024px) 14rem, 46vw'),
            'ratio' => '4/3',
            'tilt' => 'right',
            'caption' => 'Учимся первой помощи',
            'class' => 'relative z-10 -ml-[6%] mt-[26%] w-[46%] shrink-0',
        ]) ?>
      </div>
    </div>

    <div class="lg:pt-2">
      <ul class="grid grid-cols-1 gap-x-10 sm:grid-cols-2" role="list" data-reveal>
        <?php foreach ($care as [$title, $html]): ?>
          <li class="border-t border-dashed border-sage/35 py-6">
            <h3 class="font-display text-xl font-semibold uppercase leading-tight tracking-[0.03em] text-gold"><?= t($title) ?></h3>
            <p class="mt-2 leading-relaxed text-cream/85"><?= $html ?></p>
          </li>
        <?php endforeach ?>
      </ul>
      <a class="group/more mt-6 inline-block font-display text-[0.9375rem] font-semibold uppercase leading-snug tracking-btn text-gold no-underline hover:text-cream" href="/info/"><?= t('Памятка для родителей') ?><?= $moreArrow ?></a>
    </div>
  </div>
</section>

<?php /* 6. Как проходит смена + распорядок дня */ ?>
<section class="<?= $section ?>" data-block="steps" aria-labelledby="steps-title">
  <div class="wrap">
    <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] lg:gap-16">
      <div>
        <?= $this->partial('components/section-head', ['title' => 'Как проходит смена', 'id' => 'steps-title']) ?>
        <?= $this->partial('components/steps', ['steps' => $steps, 'class' => 'mt-10']) ?>
      </div>
      <?php /* На телефоне три снимка в ряд выходили по 85 px — лента с прокруткой вбок (app.css, .photo-strip), с 640 px — три в ряд */ ?>
      <div class="photo-strip" role="group" aria-label="<?= e('Фото со смены') ?>" data-scroll-region data-reveal>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('evening-circle', '(min-width: 1024px) 14rem, (min-width: 640px) 31vw, 60vw'),
            'tilt' => 'left',
            'caption' => 'Вечерний круг',
        ]) ?>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('games-chairs', '(min-width: 1024px) 14rem, (min-width: 640px) 31vw, 60vw'),
            'tilt' => 'right',
            'caption' => 'Игры всем отрядом',
            'class' => 'sm:translate-y-6',
        ]) ?>
        <?= $this->partial('components/polaroid', [
            'media' => $shot('awards-group', '(min-width: 1024px) 14rem, (min-width: 640px) 31vw, 60vw'),
            'tilt' => 'slight',
            'caption' => 'Грамоты в финале',
        ]) ?>
      </div>
    </div>

    <?php if ($schedule !== []): ?>
      <details class="group mt-16 rounded-card bg-paper shadow-card lg:mt-20">
        <summary class="flex items-center justify-between gap-6 rounded-card px-6 py-5 transition-colors hover:bg-paper/60 sm:px-8 sm:py-6">
          <span>
            <span class="block font-display text-[1.375rem] font-semibold uppercase leading-tight tracking-[0.03em] text-ink">Распорядок дня</span>
            <span class="mt-1 block text-[0.9375rem] text-ink-soft"><?= t('Как устроен обычный день на смене — от подъёма до отбоя') ?></span>
          </span>
          <span class="grid size-11 shrink-0 place-items-center rounded-full border-2 border-dashed border-moss text-forest transition-transform duration-200 group-open:rotate-180" aria-hidden="true">
            <?= icon('chevron-down', 'size-5') ?>
          </span>
        </summary>
        <div class="border-t border-dashed border-moss/45 px-6 pb-7 pt-4 sm:px-8">
          <dl class="sm:columns-2 sm:gap-14">
            <?php foreach ($schedule as [$time, $what]): ?>
              <div class="flex break-inside-avoid gap-5 border-b border-dashed border-sand-deep py-3">
                <dt class="tabular w-[7.5rem] shrink-0 font-display text-[1.0625rem] font-medium tracking-[0.02em] text-forest"><?= t($time) ?></dt>
                <dd class="text-ink"><?= t($what) ?></dd>
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
        <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)] lg:gap-12">
          <div class="lg:pt-2">
            <h3 class="font-display text-[1.75rem] font-bold uppercase leading-tight tracking-[0.02em] text-ink"><?= t((string) ($venue['name'] ?? '')) ?></h3>
            <?php if (!empty($venue['address'])): ?>
              <p class="mt-3 flex items-start gap-2 leading-snug text-ink-soft"><?= icon('map-pin', 'mt-0.5 size-[1.125rem] text-moss') ?><span><?= t((string) $venue['address']) ?></span></p>
            <?php endif ?>
            <p class="mt-5 border-t border-dashed border-moss/45 pt-5 leading-relaxed text-ink">
              <?= t((count(array_unique($base['shifts'])) > 1 ? 'Здесь пройдут смены' : 'Здесь пройдёт смена') . ' ' . $titles . '.') ?>
            </p>
            <a class="<?= $moreLink ?> mt-6" href="/svedeniya/mto/">Условия проживания<?= $moreArrow ?></a>
          </div>
          <?php if ($photos !== []): ?>
            <ul data-reveal class="grid auto-rows-[8.5rem] grid-cols-2 gap-3 sm:auto-rows-[11rem] sm:grid-cols-4 sm:gap-4" role="list">
              <?php foreach ($photos as $i => $img): ?>
                <li class="<?= $i === 0 ? 'col-span-2 row-span-2 ' : '' ?>overflow-hidden rounded-card bg-sand-deep">
                  <?= picture($img, (string) ($img['alt'] ?? ($venue['name'] ?? 'База') . ': фото ' . ($i + 1) . ' из ' . count($photos)), $i === 0 ? '(min-width: 1024px) 32rem, 100vw' : '(min-width: 1024px) 16rem, 50vw', false, 'h-full w-full object-cover') ?>
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
        <div data-reveal class="mt-10 grid grid-cols-1 items-start gap-x-14 gap-y-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
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
          <a class="<?= $moreLink ?> mt-8" href="<?= e($reviewsUrl) ?>" rel="noopener" target="_blank"><?= t($reviewsIsThread ? 'Ещё отзывы во ВКонтакте' : 'Мы во ВКонтакте') ?><?= $moreArrow ?></a>
        <?php endif ?>
      <?php else: ?>
        <div class="mt-8 flex flex-col items-start gap-5 rounded-card border-2 border-dashed border-moss/45 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
          <p class="max-w-[52ch] text-lg leading-snug text-ink"><?= t('Родители рассказывают о сменах в обсуждении в нашей группе ВКонтакте. Почитайте, как ребятам у нас.') ?></p>
          <?= $this->partial('components/button', [
              'label' => $reviewsIsThread ? 'Читать отзывы' : 'Группа ВКонтакте',
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
    <div class="wrap grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
      <div>
        <?= $this->partial('components/section-head', [
            'title' => 'Частые вопросы',
            'lead' => 'Коротко о главном. Остальные ответы — на странице для родителей.',
            'id' => 'faq-title',
        ]) ?>
        <a class="<?= $moreLink ?> mt-6" href="/info/#faq"><?= t('Все вопросы и ответы') ?><?= $moreArrow ?></a>
        <?php if (!empty($settings['phone'])): ?>
          <p class="mt-8 max-w-[30ch] leading-relaxed text-ink-soft">
            <?= t('Не нашли ответа? Позвоните:') ?>
            <a class="tabular whitespace-nowrap font-semibold text-rust-deep underline decoration-rust-deep/40 underline-offset-4 hover:decoration-rust-deep" href="<?= e(tel((string) $settings['phone'])) ?>"><?= t(Phone::pretty((string) $settings['phone'])) ?></a>
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
      'image' => photo('campfire'),
      'id' => 'cta-title',
  ]) ?>
</section>
