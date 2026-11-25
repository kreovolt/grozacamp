<?php
declare(strict_types=1);
$this->layout('layout', ['title' => 'Оболочка']);
?>
<section data-label="<?= \Groza\View\e($label) ?>"><?= $content ?></section>
