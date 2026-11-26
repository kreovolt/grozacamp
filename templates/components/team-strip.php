<?php
declare(strict_types=1);

/**
 * Полоса команды: до шести человек (фото или инициалы, имя, должность, пара строк о человеке).
 * На телефоне видны первые четыре — остальные на странице «Команда».
 * @var list<array<string,mixed>> $members
 * @var string|null $class
 */
use function Groza\View\e;
use function Groza\View\image;
use function Groza\View\picture;
use function Groza\View\t;

if ($members === []) {
    return;
}
// Инициалы: «Фамилия Имя Отчество» → «ИФ»
$initials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = array_map(static fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

    return implode('', array_reverse($letters));
};
?>
<ul class="<?= e(trim('grid gap-x-10 sm:grid-cols-2 lg:grid-cols-3 ' . ($class ?? ''))) ?>" role="list">
  <?php foreach (array_values($members) as $i => $member): ?>
    <?php
    $name = (string) ($member['name'] ?? '');
    try {
        $img = image($member['photo'] ?? null);
    } catch (\InvalidArgumentException) {
        $img = null;
    }
    ?>
    <li class="team-member <?= $i >= 4 ? 'hidden sm:flex' : 'flex' ?> gap-4 border-t border-dashed border-moss/45 py-6">
      <?php if ($img !== null): ?>
        <div class="size-16 shrink-0 overflow-hidden rounded-full bg-sand-deep ring-4 ring-paper">
          <?= picture($img, $name, '4rem', false, 'h-full w-full object-cover') ?>
        </div>
      <?php else: ?>
        <span class="grid size-16 shrink-0 place-items-center rounded-full bg-forest font-display text-xl font-semibold tracking-[0.06em] text-gold ring-4 ring-paper" aria-hidden="true"><?= e($initials($name)) ?></span>
      <?php endif ?>
      <div class="min-w-0">
        <p class="font-bold leading-snug text-ink"><?= t($name) ?></p>
        <?php if (!empty($member['role'])): ?>
          <p class="mt-1 font-display text-[0.875rem] font-medium uppercase leading-snug tracking-[0.08em] text-rust-deep"><?= t((string) $member['role']) ?></p>
        <?php endif ?>
        <?php if (!empty($member['about'])): ?>
          <p class="mt-2 line-clamp-3 text-[0.9375rem] leading-relaxed text-ink-soft"><?= t((string) $member['about']) ?></p>
        <?php endif ?>
      </div>
    </li>
  <?php endforeach ?>
</ul>
