<?php
declare(strict_types=1);

/**
 * Финальный призыв: заголовок, кнопка к сменам, телефон и WhatsApp офиса на forest.
 * С фото — фото-карточкой в естественных цветах, как у героя главной (не фоном под зелёным затемнением:
 * ночной костёр под заливкой превращался в мутное пятно). Телефон: текст, фото, контакты;
 * с 1024 px — текст и контакты слева, фото справа (.cta-grid в app.css). Без фото — текст и контакты в две колонки.
 * Подпись фото — alt из дескриптора (описание снимка из config/photos.json).
 * @var array<string,mixed> $settings
 * @var string $title
 * @var string|null $lead
 * @var string $href            куда ведёт главная кнопка
 * @var string|null $label      подпись главной кнопки
 * @var array<string,mixed>|null $image   дескриптор фото
 * @var string|null $id         id заголовка (aria-labelledby)
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\picture;
use function Groza\View\t;
use function Groza\View\tel;

$hours = array_values(array_filter([$settings['hoursWeekdays'] ?? '', $settings['hoursWeekend'] ?? '']));
$withPhoto = !empty($image);

ob_start();
?>
      <h2 class="font-display text-[clamp(2rem,1.25rem+3.4vw,4.25rem)] font-bold uppercase leading-[1.08] tracking-[0.01em] text-cream"<?= !empty($id) ? ' id="' . e($id) . '"' : '' ?>><?= t($title) ?></h2>
      <?php if (!empty($lead)): ?>
        <p class="mt-5 max-w-[46ch] text-[1.0625rem] leading-relaxed text-cream/85 sm:text-lg"><?= t($lead) ?></p>
      <?php endif ?>
      <?= $this->partial('components/button', ['label' => $label ?? 'Выбрать смену', 'href' => $href, 'size' => 'lg', 'class' => 'mt-8 w-full sm:w-auto']) ?>
<?php
$text = (string) ob_get_clean();

ob_start();
?>
      <p class="text-[1.0625rem] font-semibold text-cream/90"><?= t('Остались вопросы? Позвоните в офис') ?></p>
      <?php if (!empty($settings['phone'])): ?>
        <a class="tap tabular mt-3 inline-block whitespace-nowrap font-display text-[clamp(1.875rem,1.5rem+1.4vw,2.5rem)] font-semibold leading-none tracking-[0.02em] text-cream no-underline hover:text-white" href="<?= e(tel((string) $settings['phone'])) ?>"><?= t(Phone::pretty((string) $settings['phone'])) ?></a>
      <?php endif ?>
      <?php if ($hours): ?>
        <p class="mt-3 flex items-center gap-2 text-[0.9375rem] text-sage"><?= icon('clock', 'size-4 text-gold') ?><span><?= t(implode(', ', $hours)) ?></span></p>
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
<?php
$contacts = (string) ob_get_clean();
?>
<div class="on-dark bg-forest text-cream">
  <?php if ($withPhoto): ?>
    <div class="cta-grid wrap py-16 sm:py-20 desk:py-24">
      <div data-cta-text><?= $text ?></div>
      <div class="cta-photo relative overflow-hidden bg-forest-deep" data-cta-photo>
        <?= picture($image, (string) ($image['alt'] ?? ''), '(min-width: 1360px) 640px, (min-width: 1024px) 50vw, 100vw', false, 'absolute inset-0 h-full w-full object-cover') ?>
      </div>
      <div class="border-t border-dashed border-sage/40 pt-8" data-cta-contacts><?= $contacts ?></div>
    </div>
  <?php else: ?>
    <div class="wrap grid grid-cols-1 items-end gap-10 py-16 sm:py-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] desk:py-28">
      <div><?= $text ?></div>
      <div class="border-t border-dashed border-sage/40 pt-8 lg:border-l lg:border-t-0 lg:pb-1 lg:pl-10 lg:pt-0"><?= $contacts ?></div>
    </div>
  <?php endif ?>
</div>
