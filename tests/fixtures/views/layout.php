<?php
declare(strict_types=1);
/** Фикстура ViewTest: простейший макет. */
?>
<html><head><title><?= \Groza\View\e($title ?? 'без заголовка') ?></title></head><body data-site="<?= \Groza\View\e($site) ?>"><main><?= $content ?></main></body></html>
