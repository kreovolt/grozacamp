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

try {
    $app = require $bootstrap;
    $app->handle(\Groza\Http\Request::fromGlobals())->send();
} catch (\Throwable $e) {
    // сбой до App::handle (нет vendor/, испорчен config/local.php) или при отправке ответа: без подробностей посетителю
    if (class_exists(\Groza\Http\ErrorPage::class)) {
        \Groza\Http\ErrorPage::log($e);
        \Groza\Http\SecurityHeaders::apply(\Groza\Http\ErrorPage::response(), [])->send();
    } else {
        error_log('Groza: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка на сайте</title>'
            . '<p>На сайте произошла ошибка. Позвоните нам: <a href="tel:+73422474347">+7 (342) 247-43-47</a>'
            . ' или напишите в <a href="https://wa.me/79991239300">WhatsApp</a>.</p>';
    }
}
