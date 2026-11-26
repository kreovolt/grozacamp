<?php
declare(strict_types=1);

/**
 * «Команда» (спека §7.7): карточки по группам. Нет фото — инициалы на тёмном фоне (как в полосе команды на главной).
 * @var list<array{title:string,members:list<array<string,mixed>>}> $groups
 */
use function Groza\View\e;
use function Groza\View\image;
use function Groza\View\picture;
use function Groza\View\t;

$this->layout('layout', ['page' => 'team']);

// Инициалы: «Фамилия Имя Отчество» → «ИФ»
$initials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = array_map(static fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

    return implode('', array_reverse($letters));
};
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="team-title">
  <div class="wrap pb-14 pt-8 desk:pb-20 desk:pt-12">
    <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => 'Команда']], 'tone' => 'dark', 'class' => 'mb-8']) ?>
    <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="team-title">Команда</h1>
    <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= t('Люди, которые будут рядом с вашим ребёнком на смене: руководители, педагоги и вожатые.') ?></p>
  </div>
</section>

<div class="wrap py-12 sm:py-16 desk:py-20" data-block="team">
  <?php if ($groups === []): ?>
    <p class="max-w-[52ch] text-lg leading-relaxed text-ink-soft"><?= t('Раздел наполняется. Позвоните в офис, и мы расскажем, кто работает на смене.') ?></p>
  <?php endif ?>
  <?php foreach ($groups as $gi => $group): ?>
    <section class="<?= $gi > 0 ? 'mt-14 sm:mt-16' : '' ?>" aria-labelledby="team-group-<?= $gi ?>">
      <h2 class="border-b border-dashed border-moss/45 pb-4 font-display text-[clamp(1.5rem,1.2rem+1.1vw,2.125rem)] font-bold uppercase leading-[1.1] tracking-[0.015em] text-ink" id="team-group-<?= $gi ?>"><?= t($group['title']) ?></h2>
      <?php /* Уже 400 px две колонки по 130 px рвали слова в описаниях — одна колонка, фото слева, текст справа */ ?>
      <ul data-reveal class="mt-8 grid grid-cols-1 gap-x-4 gap-y-7 min-[400px]:grid-cols-2 min-[400px]:gap-y-9 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 desk:grid-cols-5" role="list">
        <?php foreach ($group['members'] as $m): ?>
          <?php
          $name = (string) $m['name'];
          try {
              $img = image($m['photo'] ?? null);
          } catch (\InvalidArgumentException) {
              $img = null;
          }
          ?>
          <li class="team-member min-w-0 max-[399px]:grid max-[399px]:grid-cols-[5.5rem_minmax(0,1fr)] max-[399px]:grid-rows-[auto_auto_1fr] max-[399px]:gap-x-4">
            <?php if ($img !== null): ?>
              <div class="aspect-[4/5] overflow-hidden rounded-card bg-sand-deep max-[399px]:row-span-3 max-[399px]:self-start">
                <?= picture($img, $name, '(min-width: 1024px) 14rem, 45vw', false, 'h-full w-full object-cover') ?>
              </div>
            <?php else: ?>
              <div class="grid aspect-[4/5] place-items-center rounded-card bg-sand-deep max-[399px]:row-span-3 max-[399px]:self-start" aria-hidden="true">
                <span class="font-display text-[clamp(1.75rem,1.5rem+3vw,3.5rem)] font-semibold tracking-[0.06em] text-forest/70"><?= e($initials($name)) ?></span>
              </div>
            <?php endif ?>
            <p class="mt-3 font-bold leading-snug text-ink max-[399px]:mt-0"><?= t($name) ?></p>
            <?php if (!empty($m['role'])): ?>
              <p class="max-sm:hyphenate mt-1 font-display text-[0.875rem] font-medium uppercase leading-snug tracking-[0.08em] text-rust-deep"><?= t((string) $m['role']) ?></p>
            <?php endif ?>
            <?php if (!empty($m['about'])): ?>
              <p class="max-sm:hyphenate mt-2 line-clamp-4 text-[0.9375rem] leading-relaxed text-ink-soft"><?= t((string) $m['about']) ?></p>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endforeach ?>
</div>
