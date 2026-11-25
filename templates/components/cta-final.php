<?php
declare(strict_types=1);

/**
 * Финальный призыв: заголовок, кнопка к сменам, телефон и WhatsApp офиса. Фон — фото под зелёным затемнением с топо-линиями;
 * подпись фото — alt из дескриптора (описание снимка из config/photos.json), без него фото считается украшением.
 * @var array<string,mixed> $settings
 * @var string $title
 * @var string|null $lead
 * @var string $href            куда ведёт главная кнопка
 * @var string|null $label      подпись главной кнопки
 * @var array<string,mixed>|null $image   дескриптор фото фона
 * @var string|null $id         id заголовка (aria-labelledby)
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\picture;
use function Groza\View\tel;

$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
?>
<div class="on-dark relative isolate overflow-hidden bg-forest text-cream">
  <?php if (!empty($image)): ?>
    <div class="absolute inset-0 -z-10">
      <?= picture($image, (string) ($image['alt'] ?? ''), '100vw', false, 'h-full w-full object-cover') ?>
      <div class="absolute inset-0 bg-forest/[0.86]"></div>
    </div>
  <?php endif ?>
  <?= $this->partial('components/topo', ['class' => 'pointer-events-none absolute inset-0 -z-10 h-full w-full text-sand opacity-[0.16]']) ?>

  <div class="wrap grid items-end gap-10 py-16 sm:py-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] desk:py-28">
    <div>
      <h2 class="font-display text-[clamp(2.5rem,1.6rem+3.6vw,4.5rem)] font-bold uppercase leading-[1.08] tracking-[0.01em] text-cream"<?= !empty($id) ? ' id="' . e($id) . '"' : '' ?>><?= e($title) ?></h2>
      <?php if (!empty($lead)): ?>
        <p class="mt-5 max-w-[46ch] text-[1.0625rem] leading-relaxed text-cream/85 sm:text-lg"><?= e($lead) ?></p>
      <?php endif ?>
      <?= $this->partial('components/button', ['label' => $label ?? 'Выбрать смену', 'href' => $href, 'size' => 'lg', 'class' => 'mt-8 w-full sm:w-auto']) ?>
    </div>

    <div class="border-t border-dashed border-sage/40 pt-8 lg:border-l lg:border-t-0 lg:pb-1 lg:pl-10 lg:pt-0">
      <p class="text-[1.0625rem] font-semibold text-cream/90">Остались вопросы? Позвоните в офис</p>
      <?php if (!empty($settings['phone'])): ?>
        <a class="tabular mt-3 inline-block font-display text-[clamp(1.875rem,1.5rem+1.4vw,2.5rem)] font-semibold leading-none tracking-[0.02em] text-cream no-underline hover:text-white" href="<?= e(tel((string) $settings['phone'])) ?>"><?= e(Phone::pretty((string) $settings['phone'])) ?></a>
      <?php endif ?>
      <?php if ($hours): ?>
        <p class="mt-3 flex items-center gap-2 text-[0.9375rem] text-sage"><?= icon('clock', 'size-4 text-gold') ?><?= e(implode(', ', $hours)) ?></p>
      <?php endif ?>
      <?php if (!empty($settings['whatsapp'])): ?>
        <?= $this->partial('components/button', [
            'label' => 'Написать в WhatsApp',
            'href' => 'https://wa.me/' . ltrim((string) $settings['whatsapp'], '+'),
            'variant' => 'outline-light',
            'icon' => 'message-circle',
            'class' => 'mt-6',
            'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
        ]) ?>
      <?php endif ?>
    </div>
  </div>
</div>
