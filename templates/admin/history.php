<?php
/**
 * История изменений: версии по файлам данных, откат с подтверждением.
 * @var list<array{name:string,label:string,versions:list<array{id:string,at:string,who:string,what:string}>}> $files
 * @var string|null $notice
 * @var string|null $error
 * @var string|null $errorVersion версия, у которой не отмечено подтверждение
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'История изменений', 'user' => $user, 'csrf' => $csrf]);
?>
<p class="back back-top"><a href="/admin/">← К разделам</a></p>
<h1 class="h1">История изменений</h1>
<p class="lead">Перед каждым сохранением прежнее состояние запоминается (последние 20 версий каждого раздела). Каждая строка — состояние раздела до чьей-то правки. Кнопка возвращает его целиком. Сам откат тоже попадает в историю — его можно отменить.</p>
<p class="hint">Пароли и учётные записи в историю не входят.</p>
<?php if ($notice !== null): ?>
<p class="alert alert-ok" role="status"><?= e($notice) ?></p>
<?php endif ?>
<?php if ($error !== null): ?>
<p class="alert alert-error" role="alert" id="form-banner" tabindex="-1"><?= e($error) ?></p>
<?php endif ?>
<?php foreach ($files as $file): ?>
<?php $open = $errorVersion !== null && in_array($errorVersion, array_column($file['versions'], 'id'), true); ?>
<details class="history"<?= $open ? ' open' : '' ?>>
  <summary class="history__title"><?= e($file['label']) ?> <span class="readiness__count"><?= count($file['versions']) ?></span></summary>
<?php if ($file['versions'] === []): ?>
  <p class="hint">Пока ни разу не менялось.</p>
<?php else: ?>
  <ul class="history__list">
<?php foreach ($file['versions'] as $i => $v): ?>
    <li class="history__item">
      <div class="history__text">
        <span class="history__when">Как было до правки: <?= e($v['who']) ?>, <?= e($v['at']) ?><?= $i === 0 ? ' — до последнего изменения' : '' ?></span>
<?php if ($v['what'] !== ''): ?>
        <span class="history__what"><?= e($v['what']) ?></span>
<?php endif ?>
      </div>
      <form method="post" action="/admin/history/restore/" class="history__form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="name" value="<?= e($file['name']) ?>">
        <input type="hidden" name="version" value="<?= e($v['id']) ?>">
        <label class="history__confirm"><input type="checkbox" name="confirm" value="1" required> Да, вернуть</label>
        <button type="submit" class="btn btn-ghost">Вернуть эту версию</button>
      </form>
    </li>
<?php endforeach ?>
  </ul>
<?php endif ?>
</details>
<?php endforeach ?>
