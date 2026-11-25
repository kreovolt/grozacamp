<?php
declare(strict_types=1);
/**
 * Страница 419: токен CSRF не подошёл (сессия истекла или запрос чужой).
 * Если это была отправка формы, набранный текст показывается обратно в полях только для чтения (он не сохранён).
 * @var array{items:list<array{label:string,value:string}>, truncated:bool, next:?string}|null $echo
 */
use function Groza\View\e;

$echo ??= ['items' => [], 'truncated' => false, 'next' => null];
$this->layout('admin/layout', ['title' => 'Сессия устарела', 'user' => null, 'csrf' => '']);
?>
<section class="card<?= $echo['items'] === [] ? ' card-narrow' : '' ?>" aria-labelledby="exp-title">
  <h1 id="exp-title" class="h1">Нужно обновить страницу</h1>
  <p class="alert alert-error" role="alert">Сессия устарела. Обновите страницу и повторите.</p>
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
<?php if ($echo['next'] !== null): ?>
    <a class="btn btn-primary" href="/admin/login/?next=<?= e($echo['next']) ?>">Войти заново</a>
<?php endif ?>
    <a class="btn <?= $echo['next'] !== null ? 'btn-ghost' : 'btn-primary' ?>" href="/admin/">Вернуться в админку</a>
  </p>
</section>
