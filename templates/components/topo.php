<?php
declare(strict_types=1);

/**
 * Топо-линии из макета B: горизонтали карты поверх героя и тёмных секций.
 * Цвет — currentColor, прозрачность и положение задаёт $class (обычно text-sand opacity-20 absolute inset-0).
 * Линии не растягиваются по толщине при любом соотношении сторон (non-scaling-stroke).
 * @var string|null $class
 */
use function Groza\View\e;

$lines = [
    'M-20 500 C 180 440, 360 550, 580 480 S 960 410, 1220 460',
    'M-20 460 C 190 400, 370 510, 590 440 S 970 370, 1220 420',
    'M-20 420 C 200 360, 380 470, 600 400 S 980 330, 1220 380',
    'M-20 380 C 210 320, 390 430, 610 360 S 990 290, 1220 340',
    'M-20 340 C 220 280, 400 390, 620 320 S 1000 250, 1220 300',
    'M-20 300 C 230 240, 410 350, 630 280 S 1010 210, 1220 260',
    'M-20 260 C 240 200, 420 310, 640 240 S 1020 170, 1220 220',
    'M-20 220 C 250 160, 430 270, 650 200 S 1030 130, 1220 180',
    'M-20 180 C 260 120, 440 230, 660 160 S 1040 90, 1220 140',
    'M-20 140 C 270 80, 450 190, 670 120 S 1050 50, 1220 100',
    'M-20 100 C 280 40, 460 150, 680 80 S 1060 10, 1220 60',
    'M-20 60 C 290 0, 470 110, 690 40 S 1070 -30, 1220 20',
];
// Для сверхшироких экранов (от 2240 px) — тот же рисунок вдвое длиннее: каждая линия продолжена двумя плавными дугами (S сохраняет
// гладкость стыка) в поле 2400 × 500. Иначе на 3440 px растяжка 1200 × 500 почти вдвое сильнее по ширине, чем по высоте, и линии плоские.
$wide = array_map(static function (string $d): string {
    preg_match('/^M-20 (-?[0-9]+) C ([0-9]+)/', $d, $m);
    $y = (int) $m[1];
    $dx = (int) $m[2] - 180;

    return $d . ' S ' . (1580 + $dx) . ' ' . ($y + 10) . ', ' . (1800 + $dx) . ' ' . ($y - 50)
        . ' S ' . (2180 + $dx) . ' ' . ($y - 110) . ', 2420 ' . ($y - 60);
}, $lines);
$cls = $class ?? 'pointer-events-none absolute inset-0 h-full w-full text-sand opacity-20';
?>
<svg class="topo <?= e($cls) ?> min-[2240px]:hidden" viewBox="0 0 1200 500" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true" focusable="false">
  <?php foreach ($lines as $d): ?>
    <path d="<?= $d ?>" vector-effect="non-scaling-stroke"/>
  <?php endforeach ?>
</svg>
<svg class="topo <?= e($cls) ?> hidden min-[2240px]:block" viewBox="0 0 2400 500" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true" focusable="false">
  <?php foreach ($wide as $d): ?>
    <path d="<?= $d ?>" vector-effect="non-scaling-stroke"/>
  <?php endforeach ?>
</svg>
