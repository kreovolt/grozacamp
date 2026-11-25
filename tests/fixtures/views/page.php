<?php
declare(strict_types=1);
$this->layout('layout', ['title' => 'Привет <мир>']);
?>
<p><?= \Groza\View\e($name) ?></p>
