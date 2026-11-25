<?php
declare(strict_types=1);

// Встроенный сервер PHP: существующие файлы из public/ отдаём как есть.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . rawurldecode($path);
    if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

// Корень сайта может быть и отдельной папкой public/, и общей папкой со всем проектом.
$bootstrap = is_file(__DIR__ . '/../app/bootstrap.php')
    ? __DIR__ . '/../app/bootstrap.php'
    : __DIR__ . '/app/bootstrap.php';

$app = require $bootstrap;
$app->handle(\Groza\Http\Request::fromGlobals())->send();
