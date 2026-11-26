<?php
declare(strict_types=1);

use Groza\App;

return (static function (): App {
    $root = dirname(__DIR__);
    require $root . '/vendor/autoload.php';
    $config = require $root . '/config/config.php';
    if (in_array($config['env'], ['staging', 'prod'], true)) {
        // на сайте ошибки — только в журнал: текст ошибки с путями и данными посетителю не показывается
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
    }

    return App::boot($config);
})();
