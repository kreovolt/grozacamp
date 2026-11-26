<?php
declare(strict_types=1);

/**
 * «Вакансии» (спека §7.5): герой, открытые вакансии (или «набор закрыт»), что даём, как проходит отбор, анкета.
 * @var list<array<string,mixed>> $vacancies
 * @var string $intro        richtext
 * @var string $benefits     richtext
 * @var string $selection    richtext
 * @var array<string,string> $values
 * @var array<string,string> $errors
 * @var array{positions:list<string>} $options
 * @var array<string,string>|null $guard
 */
use Groza\Forms\Fields;

use function Groza\View\e;
use function Groza\View\richtext;
use function Groza\View\photo;
use function Groza\View\picture;
use function Groza\View\t;

$this->layout('layout', ['page' => 'vacancies']);

$section = 'py-16 sm:py-20 desk:py-24';
$shot = static function (string $key, string $sizes): string {
    $img = photo($key);

    return picture($img, (string) ($img['alt'] ?? ''), $sizes, false, 'h-full w-full object-cover');
};
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="vac-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap grid grid-cols-1 gap-10 pb-14 pt-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-center lg:gap-16 desk:pb-20 desk:pt-12">
    <div>
      <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => 'Вакансии']], 'tone' => 'dark', 'class' => 'mb-8']) ?>
      <h1 class="font-display text-[clamp(2.25rem,1.5rem+3vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="vac-title">Вакансии</h1>
      <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= t('Мы собираем команду вожатых, педагогов и инструкторов на смены. Расскажите о себе в анкете, и мы перезвоним.') ?></p>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <?= $this->partial('components/button', ['label' => 'Заполнить анкету', 'href' => '#anketa', 'size' => 'lg']) ?>
        <?php if ($vacancies !== []): ?>
          <?= $this->partial('components/button', ['label' => 'Открытые вакансии', 'href' => '#vakansii', 'size' => 'lg', 'variant' => 'outline-light']) ?>
        <?php endif ?>
      </div>
    </div>
    <div class="hidden items-start lg:flex">
      <?= $this->partial('components/polaroid', [
          'media' => $shot('bonfire-group', '(min-width: 1024px) 22rem, 60vw'),
          'ratio' => '4/3',
          'tilt' => 'left',
          'caption' => 'Вечер у костра',
          'class' => 'relative z-10 w-[58%] shrink-0',
      ]) ?>
      <?= $this->partial('components/polaroid', [
          'media' => $shot('games-football', '(min-width: 1024px) 16rem, 40vw'),
          'ratio' => '4/3',
          'tilt' => 'right',
          'caption' => 'Футбол на поляне',
          'class' => 'relative z-20 -ml-[2%] mt-14 w-[48%] shrink-0 desk:-ml-[10%]', // 1024–1199: колонка узкая, при большом нахлёсте снимок закрывал подпись первого
      ]) ?>
    </div>
  </div>
</section>

<section class="<?= $section ?> scroll-mt-4" id="vakansii" data-block="openings" aria-labelledby="vakansii-title">
  <div class="wrap">
    <?= $this->partial('components/section-head', ['title' => 'Открытые вакансии', 'id' => 'vakansii-title']) ?>
    <?php if ($intro !== ''): ?>
      <div class="richtext mt-4 max-w-[62ch] text-[1.0625rem] leading-relaxed text-ink-soft sm:text-lg"><?= richtext($intro) ?></div>
    <?php endif ?>

    <?php if ($vacancies !== []): ?>
      <ul class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-2 desk:gap-8" role="list" data-reveal>
        <?php foreach ($vacancies as $v): ?>
          <?php
          $title = Fields::line($v['title'] ?? '');
          $summary = trim((string) ($v['summary'] ?? ''));
          $conditions = trim((string) ($v['conditions'] ?? ''));
          ?>
          <li class="flex flex-col rounded-card bg-paper p-6 shadow-card sm:p-8" data-vacancy>
            <h3 class="font-display text-[1.5rem] font-bold uppercase leading-tight tracking-[0.02em] text-ink"><?= t($title) ?></h3>
            <?php if ($summary !== ''): ?>
              <p class="mt-3 leading-relaxed text-ink-soft"><?= t($summary) ?></p>
            <?php endif ?>
            <?php if ($conditions !== ''): ?>
              <p class="mt-5 font-display text-[0.875rem] font-medium uppercase leading-snug tracking-[0.08em] text-rust-deep"><?= t('Условия и требования') ?></p>
              <div class="richtext mt-2 leading-relaxed text-ink-soft"><?= richtext($conditions) ?></div>
            <?php endif ?>
            <div class="mt-auto pt-6">
              <?= $this->partial('components/button', [
                  'label' => 'Откликнуться',
                  'href' => '/vacancies/?position=' . rawurlencode($title) . '#anketa',
                  'variant' => 'outline',
                  'attrs' => ['aria-label' => 'Откликнуться на вакансию «' . $title . '»'],
              ]) ?>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php else: ?>
      <div class="mt-10 flex flex-col items-start gap-5 rounded-card border-2 border-dashed border-moss/45 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <p class="max-w-[48ch] text-lg font-semibold leading-snug text-ink"><?= t('Сейчас набор закрыт, оставьте анкету на следующий сезон') ?></p>
        <?= $this->partial('components/button', ['label' => 'Заполнить анкету', 'href' => '#anketa', 'class' => 'shrink-0']) ?>
      </div>
    <?php endif ?>
  </div>
</section>

<?php if ($benefits !== '' || $selection !== ''): ?>
  <section class="bg-sand-deep <?= $section ?>" data-block="offer" aria-label="Что даём и как проходит отбор">
    <div class="wrap grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
      <?php if ($benefits !== ''): ?>
        <div>
          <?= $this->partial('components/section-head', ['title' => 'Что даём', 'id' => 'benefits-title']) ?>
          <div class="richtext mt-6 max-w-[56ch] leading-relaxed text-ink"><?= richtext($benefits) ?></div>
        </div>
      <?php endif ?>
      <?php if ($selection !== ''): ?>
        <div>
          <?= $this->partial('components/section-head', ['title' => 'Как проходит отбор', 'id' => 'selection-title']) ?>
          <div class="richtext mt-6 max-w-[56ch] leading-relaxed text-ink"><?= richtext($selection) ?></div>
        </div>
      <?php endif ?>
    </div>
  </section>
<?php endif ?>

<section class="<?= $section ?> scroll-mt-4" id="anketa" data-block="form" aria-labelledby="anketa-title">
  <div class="wrap grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
    <?= $this->partial('components/section-head', [
        'title' => 'Анкета',
        'lead' => 'Несколько коротких полей: должность, как с вами связаться и пара слов об опыте.',
        'id' => 'anketa-title',
    ]) ?>
    <div class="rounded-card bg-paper p-6 shadow-card sm:p-8">
      <?= $this->partial('forms/vacancy', ['values' => $values, 'errors' => $errors, 'options' => $options, 'guard' => $guard]) ?>
    </div>
  </div>
</section>
