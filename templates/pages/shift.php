<?php
declare(strict_types=1);

/**
 * Страница смены (спека §7.3): герой с фото, даты/длина/возраст/цена/статус, «чем займёмся», три возрастные группы,
 * где проходит, что входит в стоимость, вопросы, «Забронировать». Прошедшая смена — «Смена завершена» и ссылка на текущие.
 * @var array<string,mixed> $shift
 * @var array<string,mixed>|null $venue
 * @var bool $past
 * @var list<string> $included
 * @var list<array{question:string,answer:string}> $faq
 * @var array<string,mixed> $settings
 */
use Groza\Domain\Shifts;
use Groza\Pages\HomePage;
use Groza\Seo\JsonLd;
use Groza\Support\Dates;
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\image;
use function Groza\View\picture;
use function Groza\View\tel;

$plural = static function (int $n, string $one, string $few, string $many): string {
    $n = abs($n) % 100;
    if ($n >= 11 && $n <= 19) {
        return $many;
    }

    return match ($n % 10) {
        1 => $one,
        2, 3, 4 => $few,
        default => $many,
    };
};

$title = (string) ($shift['title'] ?? '');
$start = (string) ($shift['dateStart'] ?? '');
$end = (string) ($shift['dateEnd'] ?? '');
$days = (int) ($shift['days'] ?? 0);
if ($days < 1 && $start !== '' && $end !== '') {
    $days = Shifts::days($start, $end);
}
$ageMin = (int) ($shift['ageMin'] ?? 0);
$ageMax = (int) ($shift['ageMax'] ?? 0);
$ages = $ageMin > 0 && $ageMax > 0 ? ($ageMin === $ageMax ? (string) $ageMax : $ageMin . '–' . $ageMax) . ' ' . $plural($ageMax, 'год', 'года', 'лет') : '';
$status = (string) ($shift['status'] ?? 'open');
$closed = $status === 'closed';
$label = Shifts::STATUS_LABELS[$status] ?? '';
$price = $shift['price'] ?? null;
$priceText = is_int($price) || (is_string($price) && ctype_digit($price))
    ? number_format((int) $price, 0, '', "\u{00A0}") . "\u{00A0}₽"
    : null;
$priceNote = trim((string) ($shift['priceNote'] ?? ''));
$phone = trim((string) ($settings['phone'] ?? ''));
$activities = array_values(array_filter(array_map(static fn (mixed $a): string => is_string($a) ? trim($a) : '', (array) ($shift['activities'] ?? [])), static fn (string $a): bool => $a !== ''));
$body = trim((string) ($shift['body'] ?? ''));
// Три возрастные группы — только у смен 8–17 лет (старый сайт: «Программа поделена на три возрастные группы: 8-11, 12-14 и 15-17 лет»)
$groups = $ageMin === 8 && $ageMax === 17 ? [['8–11 лет', 'Младшая группа'], ['12–14 лет', 'Средняя группа'], ['15–17 лет', 'Старшая группа']] : [];

try {
    $img = image($shift['photo'] ?? null);
} catch (\InvalidArgumentException) {
    $img = null;
}
$bookingHref = HomePage::bookingHref($shift);
$canBook = !$past && !$closed;
// Смена «скоро» ещё не в списке формы: заявка без выбора смены
$soon = $status === 'soon';
$bookLabel = $soon ? 'Оставить заявку' : 'Забронировать';

$desc = trim((string) ($shift['lead'] ?? ''));
$this->layout('layout', [
    'page' => 'shift',
    'title' => $title . ($start !== '' && $end !== '' ? ', ' . Dates::range($start, $end) : ''),
    'description' => $desc !== '' ? $desc : 'Смена «' . $title . '» детского лагеря «Гроза» в Пермском крае.',
    'noindex' => $past,
    'jsonLd' => $past ? [] : [JsonLd::event($shift, $venue, $baseUrl)],
]);

$section = 'py-16 sm:py-20 desk:py-24';
$badges = ['open' => 'bg-moss text-white', 'few' => 'bg-gold text-ink', 'soon' => 'bg-paper text-forest', 'closed' => 'bg-ink text-cream'];
$fact = static fn (string $name, string $value): string => '<div class="border-t border-dashed border-sage/40 pt-3"><dt class="text-[0.8125rem] uppercase tracking-[0.1em] text-sage">' . e($name) . '</dt><dd class="tabular mt-1 font-display text-[1.25rem] font-semibold leading-tight tracking-[0.02em] text-cream">' . e($value) . '</dd></div>';
?>
<section class="on-dark relative isolate overflow-hidden bg-forest text-cream" data-block="hero" aria-labelledby="shift-title">
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.14]']) ?>
  <div class="wrap grid gap-10 pb-14 pt-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center lg:gap-14 desk:pb-20 desk:pt-12">
    <div>
      <?= $this->partial('partials/breadcrumbs', ['items' => [['label' => 'Главная', 'href' => '/'], ['label' => 'Купить путёвку', 'href' => '/booking/'], ['label' => $title]], 'tone' => 'dark', 'class' => 'mb-8']) ?>
      <?php if ($past): ?>
        <p class="inline-block rounded-[4px] bg-ink px-2.5 py-1.5 font-display text-[0.8125rem] font-semibold uppercase leading-none tracking-[0.12em] text-cream">Смена завершена</p>
      <?php elseif ($label !== ''): ?>
        <p class="inline-block rounded-[4px] px-2.5 py-1.5 font-display text-[0.8125rem] font-semibold uppercase leading-none tracking-[0.12em] <?= $badges[$status] ?? $badges['open'] ?>"><?= e($label) ?></p>
      <?php endif ?>
      <h1 class="mt-4 font-display text-[clamp(2.5rem,1.3rem+4.8vw,4rem)] font-bold uppercase leading-[1.05] tracking-[0.01em] text-[#f4efe3]" id="shift-title"><?= e($title) ?></h1>
      <?php if ($desc !== ''): ?>
        <p class="mt-5 max-w-[34rem] text-[1.0625rem] leading-relaxed text-cream/90 sm:text-lg"><?= e($desc) ?></p>
      <?php endif ?>

      <dl class="mt-8 grid max-w-[34rem] grid-cols-2 gap-x-6 gap-y-5" data-block="facts">
        <?php if ($start !== '' && $end !== ''): ?>
          <?= $fact('Даты', Dates::range($start, $end)) ?>
        <?php endif ?>
        <?php if ($days > 0): ?>
          <?= $fact('Длительность', $days . ' ' . $plural($days, 'день', 'дня', 'дней')) ?>
        <?php endif ?>
        <?php if ($ages !== ''): ?>
          <?= $fact('Возраст', $ages) ?>
        <?php endif ?>
        <?php if ($priceText !== null): ?>
          <?= $fact('Стоимость', $priceText) ?>
        <?php endif ?>
      </dl>
      <?php if ($priceText !== null && $priceNote !== ''): ?>
        <p class="mt-3 max-w-[34rem] text-[0.9375rem] text-sage"><?= e($priceNote) ?></p>
      <?php elseif ($priceText === null): ?>
        <p class="mt-5 max-w-[34rem] font-semibold text-cream">Стоимость уточняйте в офисе<?php if ($phone !== ''): ?>: <a class="tabular whitespace-nowrap underline decoration-gold/60 underline-offset-4 hover:decoration-gold" href="<?= e(tel($phone)) ?>"><?= e(Phone::pretty($phone)) ?></a><?php endif ?></p>
      <?php endif ?>

      <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <?php if ($canBook): ?>
          <?= $this->partial('components/button', ['label' => $bookLabel, 'href' => $bookingHref, 'size' => 'lg', 'attrs' => ['aria-label' => ($soon ? 'Оставить заявку на смену «' : 'Забронировать смену «') . $title . '»']]) ?>
        <?php else: ?>
          <?= $this->partial('components/button', ['label' => $past ? 'Смотреть текущие смены' : 'Выбрать другую смену', 'href' => '/booking/', 'size' => 'lg']) ?>
        <?php endif ?>
      </div>
    </div>

    <?php if ($img !== null): ?>
      <div class="relative aspect-[4/3] overflow-hidden rounded-card bg-forest-deep shadow-card">
        <?= picture($img, (string) ($img['alt'] ?? 'Смена «' . $title . '»'), '(min-width: 1024px) 40rem, 100vw', true, 'absolute inset-0 h-full w-full object-cover') ?>
      </div>
    <?php endif ?>
  </div>
</section>

<?php if ($past): ?>
  <div class="bg-sand-deep" data-block="past">
    <div class="wrap flex flex-col items-start gap-4 py-8 sm:flex-row sm:items-center sm:justify-between">
      <p class="max-w-[52ch] text-lg font-semibold leading-snug text-ink">Эта смена уже прошла. Посмотрите смены, на которые идёт набор.</p>
      <?= $this->partial('components/button', ['label' => 'Текущие смены', 'href' => '/booking/', 'class' => 'shrink-0']) ?>
    </div>
  </div>
<?php endif ?>

<?php if ($activities !== [] || $body !== '' || $groups !== []): ?>
  <section class="<?= $section ?>" data-block="program" aria-labelledby="program-title">
    <div class="wrap grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-16">
      <?php if ($activities !== []): ?>
        <div>
          <?= $this->partial('components/section-head', ['title' => 'Чем займёмся', 'id' => 'program-title']) ?>
          <ul class="mt-8 border-t border-dashed border-moss/50" role="list">
            <?php foreach ($activities as $a): ?>
              <li class="flex gap-3.5 border-b border-dashed border-moss/50 py-4 leading-snug">
                <?= icon('check', 'mt-0.5 size-5 shrink-0 text-moss') ?>
                <span class="text-[1.0625rem] text-ink"><?= e($a) ?></span>
              </li>
            <?php endforeach ?>
          </ul>
        </div>
      <?php endif ?>
      <div<?= $activities === [] ? ' class="lg:col-span-2"' : '' ?>>
        <?php if ($activities === []): ?>
          <h2 class="sr-only" id="program-title">О смене</h2>
        <?php endif ?>
        <?php if ($body !== ''): ?>
          <div class="richtext max-w-[62ch] leading-relaxed text-ink-soft"><?= $body ?></div>
        <?php endif ?>
        <?php if ($groups !== []): ?>
          <div class="<?= $body !== '' ? 'mt-10' : '' ?>">
            <h3 class="font-display text-[1.375rem] font-semibold uppercase leading-tight tracking-[0.03em] text-ink">Три возрастные группы</h3>
            <ul class="mt-4 grid grid-cols-3 gap-2 sm:gap-3" role="list">
              <?php foreach ($groups as [$range, $name]): ?>
                <li class="rounded-card bg-paper px-3 py-3.5 shadow-card sm:px-4 sm:py-4">
                  <p class="tabular font-display text-[1.125rem] font-semibold leading-none text-ink sm:text-[1.5rem]"><?= e($range) ?></p>
                  <p class="mt-1.5 text-[0.9375rem] text-ink-soft"><?= e($name) ?></p>
                </li>
              <?php endforeach ?>
            </ul>
          </div>
        <?php endif ?>
      </div>
    </div>
  </section>
<?php endif ?>

<?php if (!empty($venue['name'])): ?>
  <section class="bg-sand-deep <?= $section ?>" data-block="venue" aria-labelledby="venue-title">
    <div class="wrap grid gap-8 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)] lg:gap-12">
      <div>
        <?= $this->partial('components/section-head', ['title' => 'Где проходит', 'id' => 'venue-title']) ?>
        <p class="mt-6 font-display text-[1.5rem] font-bold uppercase leading-tight tracking-[0.02em] text-ink"><?= e((string) $venue['name']) ?></p>
        <?php if (!empty($venue['address'])): ?>
          <p class="mt-3 flex items-start gap-2 leading-snug text-ink-soft"><?= icon('map-pin', 'mt-0.5 size-[1.125rem] shrink-0 text-moss') ?><span><?= e((string) $venue['address']) ?></span></p>
        <?php endif ?>
        <a class="group/more mt-6 inline-block font-display text-[0.9375rem] font-semibold uppercase leading-snug tracking-btn text-rust-deep no-underline hover:text-rust-dark" href="/svedeniya/mto/">Условия проживания&nbsp;<?= icon('arrow-right', 'inline-block size-4 align-[-0.2em] transition-transform group-hover/more:translate-x-0.5') ?></a>
      </div>
      <?php
      $photos = [];
      foreach (array_slice((array) ($venue['photos'] ?? []), 0, 3) as $value) {
          try {
              $p = image(is_array($value) || is_string($value) ? $value : null);
          } catch (\InvalidArgumentException) {
              $p = null;
          }
          if ($p !== null) {
              $photos[] = $p;
          }
      }
      ?>
      <?php if ($photos !== []): ?>
        <ul class="grid grid-cols-3 gap-3 sm:gap-4" role="list">
          <?php foreach ($photos as $i => $p): ?>
            <li class="aspect-[3/4] overflow-hidden rounded-card bg-sand-deep">
              <?= picture($p, (string) ($p['alt'] ?? $venue['name'] . ': фото ' . ($i + 1)), '(min-width: 1024px) 14rem, 33vw', false, 'h-full w-full object-cover') ?>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
  </section>
<?php endif ?>

<?php if ($included !== []): ?>
  <section class="<?= $section ?>" data-block="included" aria-labelledby="included-title">
    <div class="wrap">
      <?= $this->partial('components/section-head', ['title' => 'Что входит в стоимость', 'id' => 'included-title']) ?>
      <?= $this->partial('components/included', ['items' => $included, 'ctaHref' => $canBook ? $bookingHref : null, 'class' => 'mt-10']) ?>
    </div>
  </section>
<?php endif ?>

<?php if ($faq !== []): ?>
  <section class="bg-paper <?= $section ?>" data-block="faq" aria-labelledby="faq-title">
    <div class="wrap grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
      <?= $this->partial('components/section-head', ['title' => 'Частые вопросы', 'id' => 'faq-title']) ?>
      <?= $this->partial('components/faq-list', ['items' => $faq]) ?>
    </div>
  </section>
<?php endif ?>

<section data-block="cta">
  <?= $this->partial('components/cta-final', [
      'title' => $canBook ? ($soon ? 'Оставьте заявку' : 'Забронируйте место') : 'Выберите смену',
      'lead' => $canBook ? 'Оставьте заявку, и офис перезвонит. Или позвоните сами.' : 'Посмотрите смены, на которые идёт набор.',
      'href' => $canBook ? $bookingHref : '/booking/#smeny',
      'label' => $canBook ? $bookLabel : 'Смотреть смены',
      'settings' => $settings,
  ]) ?>
</section>
