<?php declare(strict_types=1); ?>
<div><?= $this->partial('partials/greet', ['who' => 'Аня']) ?>|<?= isset($who) ? 'утечка' : 'чисто' ?></div>
