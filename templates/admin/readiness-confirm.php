<?php
declare(strict_types=1);
/**
 * Вопрос перед удалением или скрытием записи, после которого не будет выполнен пункт приказа № 201.
 * Та же отправка повторяется с отметкой confirm_readiness=1.
 * @var string $collection
 * @var array{label:string} $def
 * @var string $heading
 * @var string $action
 * @var array<string,string> $hidden
 * @var string $button
 * @var list<string> $lost
 * @var string $tail
 * @var array{login:string,name:string}|null $user
 * @var string $csrf
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Подтвердите', 'user' => $user, 'csrf' => $csrf]);
$when = str_ends_with($action, '/delete/') ? 'После удаления' : 'После изменения видимости';
?>
<section class="card" aria-labelledby="rc-title">
  <h1 id="rc-title" class="h1">Сайт перестанет соответствовать приказу</h1>
  <p class="del-name">«<?= e($heading) ?>» · <?= e($def['label']) ?></p>
<?= $this->partial('admin/_readiness-lost', ['lost' => $lost, 'when' => $when, 'tail' => $tail, 'confirmId' => 'readiness-confirm']) ?>
  <form method="post" action="<?= e($action) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php foreach ($hidden as $k => $v): ?>
    <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e($v) ?>">
<?php endforeach ?>
    <div class="confirm-empty" id="readiness-confirm">
      <label class="choice choice--check"><input type="checkbox" name="confirm_readiness" value="1" required> Сохранить всё равно</label>
    </div>
    <p class="actions">
      <button type="submit" class="btn btn-danger"><?= e($button) ?></button>
      <a class="btn btn-ghost" href="/admin/<?= e($collection) ?>/">Нет, оставить как было</a>
    </p>
  </form>
</section>
