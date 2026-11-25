<?php
/**
 * Стартовая страница: разделы админки.
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 * @var list<array{name:string,label:string,count:int}> $sections
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Админка', 'user' => $user, 'csrf' => $csrf]);
?>
<section aria-labelledby="home-title">
  <h1 id="home-title" class="h1">Админка</h1>
  <p class="lead">Здравствуйте, <?= e((string) ($user['name'] ?? '')) ?>. Выберите, что изменить.</p>
  <ul class="tiles">
<?php foreach ($sections as $s): ?>
    <li><a class="tile" href="/admin/<?= e($s['name']) ?>/"><span class="tile__name"><?= e($s['label']) ?></span><span class="tile__count"><?= (int) $s['count'] ?></span></a></li>
<?php endforeach ?>
  </ul>
  <p class="back"><a href="/admin/password/">Сменить пароль</a></p>
</section>
