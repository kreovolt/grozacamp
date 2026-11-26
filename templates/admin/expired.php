<?php
declare(strict_types=1);
/**
 * Отправка формы админки не принята, набранное не должно пропасть:
 *  - reason=csrf (419): токен CSRF не подошёл (сессия устарела или запрос чужой);
 *  - reason=auth (401): токен верен, но вход истёк из-за бездействия.
 * Если это была отправка формы, набранный текст показывается обратно в полях только для чтения (он не сохранён).
 * @var array{items:list<array{label:string,value:string}>, truncated:bool, next:?string}|null $echo
 * @var string|null $reason
 */
use function Groza\View\e;

$echo ??= ['items' => [], 'truncated' => false, 'next' => null];
$auth = ($reason ?? 'csrf') === 'auth';
$this->layout('admin/layout', ['title' => $auth ? 'Сессия истекла' : 'Сессия устарела', 'user' => null, 'csrf' => '']);
$login = '/admin/login/' . ($echo['next'] !== null ? '?next=' . $echo['next'] : '');
?>
<section class="card<?= $echo['items'] === [] ? ' card-narrow' : '' ?>" aria-labelledby="exp-title">
<?php if ($auth): ?>
  <h1 id="exp-title" class="h1">Сессия истекла</h1>
  <p class="alert alert-error" role="alert">Вы вышли из админки из-за бездействия — изменения не сохранены.</p>
<?php else: ?>
  <h1 id="exp-title" class="h1">Нужно обновить страницу</h1>
  <p class="alert alert-error" role="alert">Сессия устарела. Обновите страницу и повторите.</p>
<?php endif ?>
<?php if ($echo['items'] !== []): ?>
  <p><strong>Введённое не сохранено.</strong> Скопируйте текст, войдите заново и вставьте его. Ниже — всё, что вы набрали (оформление в этом показе не видно). Копия набранного ещё хранится в этом браузере: после входа откройте ту же страницу, и редактор предложит вернуть черновик.</p>
<?php if ($echo['truncated']): ?>
  <p class="alert alert-error">Часть текста не поместилась на этой странице. Если текст был очень длинным, вернитесь назад в браузере (кнопка «Назад») и скопируйте его оттуда.</p>
<?php endif ?>
  <div class="echo">
<?php foreach ($echo['items'] as $i => $item): ?>
    <div class="field">
      <label for="echo-<?= (int) $i ?>"><?= e($item['label']) ?></label>
      <textarea id="echo-<?= (int) $i ?>" readonly rows="<?= min(14, max(3, substr_count($item['value'], "\n") + 2)) ?>"><?= e($item['value']) ?></textarea>
    </div>
<?php endforeach ?>
  </div>
<?php endif ?>
  <p class="actions">
<?php if ($echo['next'] !== null || $auth): ?>
    <a class="btn btn-primary" href="<?= e($login) ?>">Войти заново</a>
<?php endif ?>
    <a class="btn <?= $echo['next'] !== null || $auth ? 'btn-ghost' : 'btn-primary' ?>" href="/admin/">Вернуться в админку</a>
  </p>
</section>
