<?php
declare(strict_types=1);

/**
 * «Позвоните или напишите» — когда письмо не ушло. Телефон и WhatsApp берутся из настроек.
 * @var string|null $intro
 * @var array<string,mixed> $settings
 */
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\t;
use function Groza\View\tel;

$phone = trim((string) ($settings['phone'] ?? ''));
$wa = preg_replace('/\D+/', '', (string) ($settings['whatsapp'] ?? '')) ?? '';
?>
<p class="text-lg font-semibold text-ink"><?= t(($intro ?? '') . ' Позвоните нам или напишите в WhatsApp — оформим заявку по телефону.') ?></p>
<ul class="mt-4 space-y-2">
  <?php if ($phone !== ''): ?>
    <li><a class="tap tabular whitespace-nowrap font-display text-2xl font-semibold text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="<?= e(tel($phone)) ?>"><?= t(Phone::pretty($phone)) ?></a></li>
  <?php endif ?>
  <?php if ($wa !== ''): ?>
    <li><a class="tap font-semibold text-rust-deep underline decoration-rust-deep/40 hover:decoration-rust-deep" href="https://wa.me/<?= e($wa) ?>" rel="noopener"><?= t('Написать в WhatsApp') ?></a></li>
  <?php endif ?>
</ul>
