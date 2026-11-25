<?php
declare(strict_types=1);

/**
 * Карточка смены (главная, «Купить путёвку»): фото со статусом, название, даты, длительность, возраст, база,
 * коротко о смене, цена, «Забронировать» и «Подробнее». У закрытой смены — плашка «Мест нет», без «Забронировать».
 * @var array<string,mixed> $shift
 * @var array<string,mixed>|null $venue
 * @var string $bookingHref     куда ведёт «Забронировать» (форма заявки с выбранной сменой)
 * @var int|null $level         уровень заголовка карточки, 3 по умолчанию
 * @var string|null $sizes      sizes для фото (по умолчанию — сетка в три колонки)
 */
use Groza\Domain\Shifts;
use Groza\Support\Dates;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\image;
use function Groza\View\picture;

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
$slug = (string) ($shift['slug'] ?? '');
$status = (string) ($shift['status'] ?? 'open');
$closed = $status === 'closed';
$start = (string) ($shift['dateStart'] ?? '');
$end = (string) ($shift['dateEnd'] ?? '');
$days = (int) ($shift['days'] ?? 0);
if ($days < 1 && $start !== '' && $end !== '') {
    $days = Shifts::days($start, $end);
}
$ageMin = (int) ($shift['ageMin'] ?? 0);
$ageMax = (int) ($shift['ageMax'] ?? 0);
$ages = $ageMin > 0 && $ageMax > 0
    ? ($ageMin === $ageMax ? $ageMax : $ageMin . '–' . $ageMax) . ' ' . $plural($ageMax, 'год', 'года', 'лет')
    : '';
$facts = array_values(array_filter([
    $days > 0 ? $days . ' ' . $plural($days, 'день', 'дня', 'дней') : '',
    $ages,
]));
$price = $shift['price'] ?? null;
// Разряды и знак рубля — неразрывными пробелами: «60 000 ₽» не переносится по частям
$priceText = is_int($price) || (is_string($price) && ctype_digit($price))
    ? number_format((int) $price, 0, '', "\u{00A0}") . "\u{00A0}₽"
    : null;
$priceNote = trim((string) ($shift['priceNote'] ?? ''));

try {
    $img = image($shift['photo'] ?? null);
} catch (\InvalidArgumentException) {
    $img = null; // ключ фото не найден в манифесте — карточка без фото, но страница работает
}

$badges = [
    'open' => 'bg-moss text-white',
    'few' => 'bg-gold text-ink',
    'soon' => 'bg-paper text-forest',
    'closed' => 'bg-ink text-cream',
];
$label = Shifts::STATUS_LABELS[$status] ?? '';
$tag = 'h' . max(2, min(4, (int) ($level ?? 3)));
$detailsHref = '/program/' . rawurlencode($slug) . '/';
?>
<article class="shift-card group/card flex flex-col overflow-hidden rounded-card bg-paper shadow-card">
  <div class="relative aspect-[3/2] overflow-hidden bg-sand-deep">
    <?php if ($img !== null): ?>
      <?= picture($img, (string) ($img['alt'] ?? 'Смена «' . $title . '»'), $sizes ?? '(min-width: 1024px) 26rem, (min-width: 768px) 50vw, 100vw', false, 'h-full w-full object-cover transition-transform duration-500 ease-out motion-safe:group-hover/card:scale-[1.03]') ?>
    <?php endif ?>
    <?php if ($label !== ''): ?>
      <p class="absolute left-4 top-4 rounded-[4px] px-2.5 py-1.5 font-display text-[0.8125rem] font-semibold uppercase leading-none tracking-[0.12em] shadow-sm <?= $badges[$status] ?? $badges['open'] ?>"><?= e($label) ?></p>
    <?php endif ?>
  </div>

  <div class="flex flex-1 flex-col px-5 pb-6 pt-5 sm:px-6">
    <<?= $tag ?> class="font-display text-[1.75rem] font-bold uppercase leading-none tracking-[0.02em] text-ink"><?= e($title) ?></<?= $tag ?>>

    <?php if ($start !== '' && $end !== ''): ?>
      <p class="mt-3.5 flex items-center gap-2 font-semibold text-ink">
        <?= icon('calendar', 'size-[1.125rem] text-moss') ?>
        <span class="tabular"><?= e(Dates::range($start, $end)) ?></span>
      </p>
    <?php endif ?>
    <?php if ($facts !== []): ?>
      <p class="mt-1 pl-[1.625rem] text-[0.9375rem] text-ink-soft"><?= e(implode(' · ', $facts)) ?></p>
    <?php endif ?>

    <?php if (!empty($shift['lead'])): ?>
      <p class="mt-4 line-clamp-4 text-[0.9375rem] leading-relaxed text-ink-soft"><?= e((string) $shift['lead']) ?></p>
    <?php endif ?>

    <?php if (!empty($venue['name'])): ?>
      <p class="mt-4 flex items-start gap-2 text-[0.9375rem] text-ink-soft">
        <?= icon('map-pin', 'mt-0.5 size-[1.125rem] text-moss') ?>
        <span><?= e((string) $venue['name']) ?></span>
      </p>
    <?php endif ?>

    <div class="mt-auto pt-5">
      <?php /* высота под цену с пояснением: у соседних карточек линия и кнопки на одном уровне */ ?>
      <div class="min-h-[4.75rem] border-t border-dashed border-moss/40 pt-4">
        <?php if ($priceText !== null): ?>
          <p class="tabular font-display text-[1.75rem] font-semibold leading-none text-ink"><?= e($priceText) ?></p>
          <?php if ($priceNote !== ''): ?>
            <p class="mt-1.5 text-[0.875rem] leading-snug text-ink-soft"><?= e($priceNote) ?></p>
          <?php endif ?>
        <?php else: ?>
          <p class="font-semibold leading-snug text-ink">Стоимость уточняйте в офисе</p>
        <?php endif ?>
      </div>

      <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3">
        <?php if (!$closed): ?>
          <?= $this->partial('components/button', [
              'label' => $status === 'soon' ? 'Оставить заявку' : 'Забронировать',
              'href' => $bookingHref,
              'attrs' => ['aria-label' => ($status === 'soon' ? 'Оставить заявку на смену «' : 'Забронировать смену «') . $title . '»'],
          ]) ?>
          <a class="group/more inline-flex min-h-11 items-center gap-1.5 font-display text-[0.9375rem] font-semibold uppercase tracking-btn text-rust-deep no-underline hover:text-rust-dark" href="<?= e($detailsHref) ?>" aria-label="<?= e('Подробнее о смене «' . $title . '»') ?>">
            Подробнее <?= icon('arrow-right', 'size-4 transition-transform group-hover/more:translate-x-0.5') ?>
          </a>
        <?php else: ?>
          <?= $this->partial('components/button', [
              'label' => 'Подробнее о смене',
              'href' => $detailsHref,
              'variant' => 'outline',
              'attrs' => ['aria-label' => 'Подробнее о смене «' . $title . '»'],
          ]) ?>
        <?php endif ?>
      </div>
    </div>
  </div>
</article>
