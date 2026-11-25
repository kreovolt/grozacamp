<?php
declare(strict_types=1);

use Groza\App;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

return App::boot(require $root . '/config/config.php');
