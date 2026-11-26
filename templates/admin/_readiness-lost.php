<?php
declare(strict_types=1);
/**
 * Предупреждение: после изменения не будут выполнены пункты приказа № 201 (спека §10.4).
 * @var list<string> $lost «п. 9 б) Условия проживания: База „Лесная“»
 * @var string $when «После сохранения» / «После удаления» / «После изменения видимости»
 * @var string $tail « — сайт перестанет соответствовать приказу. Сохранить всё равно?»
 * @var string $confirmId где отметка «… всё равно»
 */
use function Groza\View\e;

?>
<div class="alert alert-error summary" role="alert" id="error-summary" tabindex="-1">
<?php if (count($lost) === 1): ?>
  <p class="summary__title"><?= e($when) ?> не будет заполнен <?= e($lost[0]) ?><?= e($tail) ?></p>
<?php else: ?>
  <p class="summary__title"><?= e($when) ?> не будут заполнены:</p>
  <ul>
<?php foreach ($lost as $item): ?>
    <li><?= e($item) ?></li>
<?php endforeach ?>
  </ul>
  <p><?= e(ltrim($tail, ' —')) ?></p>
<?php endif ?>
  <p>Ничего не записано. Если так и нужно, отметьте <a href="#<?= e($confirmId) ?>">«Сохранить всё равно»</a> и отправьте ещё раз.</p>
</div>
