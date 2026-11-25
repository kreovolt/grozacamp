<?php
declare(strict_types=1);

// Встроенный сервер PHP (разработка): существующие файлы из public/ отдаём как есть, но только лежащие внутри public/.
if (PHP_SAPI === 'cli-server') {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (\Groza\Http\StaticFiles::servable(__DIR__, parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')) {
        return false;
    }
}

// Корень сайта может быть и отдельной папкой public/, и общей папкой со всем проектом.
$bootstrap = is_file(__DIR__ . '/../app/bootstrap.php')
    ? __DIR__ . '/../app/bootstrap.php'
    : __DIR__ . '/app/bootstrap.php';

$app = require $bootstrap;
$app->handle(\Groza\Http\Request::fromGlobals())->send();
