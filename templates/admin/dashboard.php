<?php
declare(strict_types=1);
/**
 * Главная: «Готовность к проверке», последние изменения, разделы админки.
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 * @var list<array{name:string,label:string,count:int}> $sections
 * @var array<string,string> $textGroups
 * @var bool $ready
 * @var int $total
 * @var int $failedCount
 * @var string $summary
 * @var list<array{label:string,items:list<array<string,mixed>>}> $groups
 * @var list<array<string,mixed>> $warnings
 * @var list<array{who:string,when:string,what:string,title:string}> $recent
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Готовность к проверке', 'user' => $user, 'csrf' => $csrf]);
?>
<section class="readiness" aria-labelledby="home-title">
  <h1 id="home-title" class="h1">Готовность к проверке</h1>
  <p class="lead">Здравствуйте, <?= e((string) ($user['name'] ?? '')) ?>. Так выглядит сайт для проверяющих: что уже заполнено, а чего ещё не хватает.</p>

  <p class="readiness__summary <?= $ready ? 'readiness__summary--ok' : 'readiness__summary--bad' ?>" role="status">
    <span class="readiness__summary-title"><?= e($summary) ?></span>
    <span class="readiness__summary-note"><?= $ready ? 'Заполнены все обязательные пункты: ' . (int) $total : 'Заполнено ' . (int) ($total - $failedCount) . ' из ' . (int) $total . '.' ?></span>
  </p>

<?php foreach ($groups as $g): ?>
  <h2 class="readiness__section"><?= e($g['label']) ?> <span class="readiness__count"><?= count($g['items']) ?></span></h2>
  <ul class="readiness__list">
<?php foreach ($g['items'] as $item): ?>
    <li class="readiness__item readiness__item--bad">
      <span class="readiness__label"><?= e((string) $item['label']) ?></span>
      <a class="btn btn-primary readiness__fix" href="<?= e((string) $item['fix']) ?>">Заполнить</a>
    </li>
<?php endforeach ?>
  </ul>
<?php endforeach ?>

<?php if ($warnings !== []): ?>
  <div class="readiness__warnings">
    <h2 class="form-heading">Предупреждения</h2>
    <p class="hint">Запуск не блокируют, но их стоит просмотреть.</p>
    <ul class="readiness__list">
<?php foreach ($warnings as $w): ?>
      <li class="readiness__item readiness__item--warn">
        <span class="readiness__label"><?= e((string) $w['label']) ?></span>
        <a class="btn btn-ghost readiness__fix" href="<?= e((string) $w['fix']) ?>">Открыть</a>
      </li>
<?php endforeach ?>
    </ul>
  </div>
<?php endif ?>
</section>

<section class="changes" aria-labelledby="changes-title">
  <h2 id="changes-title" class="form-heading">Последние изменения</h2>
<?php if ($recent === []): ?>
  <p class="hint">Пока изменений нет.</p>
<?php else: ?>
  <ul class="changes__list">
<?php foreach ($recent as $c): ?>
    <li class="changes__item">
      <span class="changes__when"><?= e($c['when']) ?></span>
      <span class="changes__what"><strong><?= e($c['who']) ?></strong> <?= e($c['what']) ?><?= $c['title'] !== '' ? ' «' . e($c['title']) . '»' : '' ?></span>
    </li>
<?php endforeach ?>
  </ul>
<?php endif ?>
  <p class="back"><a href="/admin/history/">Вся история и откат →</a></p>
</section>

<section aria-labelledby="sections-title">
  <h2 id="sections-title" class="form-heading">Разделы</h2>
  <ul class="tiles">
<?php foreach ($sections as $s): ?>
    <li><a class="tile" href="/admin/<?= e($s['name']) ?>/"><span class="tile__name"><?= e($s['label']) ?></span><span class="tile__count"><?= (int) $s['count'] ?></span></a></li>
<?php endforeach ?>
  </ul>
  <h2 class="form-heading">Тексты сайта</h2>
  <ul class="tiles">
<?php foreach ($textGroups as $slug => $label): ?>
    <li><a class="tile" href="/admin/texts/<?= e($slug) ?>/"><span class="tile__name"><?= e($label) ?></span></a></li>
<?php endforeach ?>
    <li><a class="tile" href="/admin/settings/"><span class="tile__name">Настройки и контакты</span></a></li>
  </ul>
  <h2 class="form-heading">Надёжность</h2>
  <ul class="tiles">
    <li><a class="tile" href="/admin/history/"><span class="tile__name">История изменений</span></a></li>
    <li><a class="tile" href="/admin/backup/"><span class="tile__name">Скачать резервную копию</span></a></li>
  </ul>
  <p class="back"><a href="/admin/password/">Сменить пароль</a></p>
</section>
