<?php
declare(strict_types=1);

/**
 * Кнопка-ссылка или <button>. Oswald, верхний регистр, радиус 6 px.
 * @var string $label
 * @var string|null $href      нет ссылки → <button>
 * @var string|null $variant   primary (rust-dark, белый текст, 5.3:1) | outline (на светлом) | outline-light (на тёмном)
 * @var string|null $size      sm | md | lg
 * @var string|null $icon      имя иконки из спрайта (слева от текста)
 * @var string|null $type      для <button>: button | submit
 * @var string|null $class
 * @var array<string,string>|null $attrs  доп. атрибуты (aria-*, data-*, target, rel…)
 */
use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\t;

$variants = [
    'primary' => 'bg-rust-dark text-white shadow-cta hover:bg-rust-deep',
    'outline' => 'border-2 border-forest text-forest hover:bg-forest hover:text-cream',
    'outline-light' => 'border-2 border-cream/80 text-cream hover:border-cream hover:bg-cream hover:text-forest',
];
$sizes = [
    'sm' => 'min-h-11 px-4 text-[0.9375rem]',
    'md' => 'min-h-12 px-[1.375rem] text-[0.9375rem]',
    'lg' => 'min-h-14 px-7 text-base',
];
$classes = implode(' ', array_filter([
    'inline-flex items-center justify-center gap-2.5 rounded-btn font-display font-semibold uppercase leading-none tracking-btn no-underline',
    'transition-[background-color,border-color,color,transform] duration-150 ease-out active:translate-y-px',
    $variants[$variant ?? 'primary'] ?? $variants['primary'],
    $sizes[$size ?? 'md'] ?? $sizes['md'],
    $class ?? '',
]));
$extra = '';
foreach ($attrs ?? [] as $name => $value) {
    $extra .= ' ' . e($name) . '="' . e($value) . '"';
}
$inner = (!empty($icon) ? icon($icon, 'size-[1.15em]') : '') . '<span>' . t($label) . '</span>';
?>
<?php if (!empty($href)): ?>
<a class="<?= e($classes) ?>" href="<?= e($href) ?>"<?= $extra ?>><?= $inner ?></a>
<?php else: ?>
<button class="<?= e($classes) ?>" type="<?= e($type ?? 'button') ?>"<?= $extra ?>><?= $inner ?></button>
<?php endif ?>
