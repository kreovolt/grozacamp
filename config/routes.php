<?php
declare(strict_types=1);

use Groza\App;
use Groza\Content\Nav;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
use Groza\Pages\HomePage;

return static function (Router $router, App $app): void {
    $router->get('/', new HomePage($app));

    // ВРЕМЕННЫЕ ЗАГЛУШКИ (Task 4), чтобы пункты меню не вели в 404. Их заменяют:
    // /booking/ — Task 9, /info/ и /vacancies/ — Task 10, /svedeniya/ и подстраницы — Task 11.
    $home = ['label' => 'Главная', 'href' => '/'];
    $placeholder = static fn (string $title, array $crumbs): callable =>
        static fn (Request $r, array $params): Response => Response::html(
            $app->view()->render('pages/placeholder', ['title' => $title, 'crumbs' => $crumbs])
        );
    foreach (Nav::main() as $item) {
        $router->get($item['href'], $placeholder($item['label'], [$home, ['label' => $item['label']]]));
        foreach ($item['children'] ?? [] as $child) {
            $router->get($child['href'], $placeholder($child['label'], [
                $home,
                ['label' => $item['label'], 'href' => $item['href']],
                ['label' => $child['label']],
            ]));
        }
    }

    // Витрина дизайн-системы для разработки (не для посетителей): /_styleguide/ только в dev и test.
    if (in_array($app->config()['env'], ['dev', 'test'], true)) {
        $router->get('/_styleguide/', static fn (Request $r, array $params): Response => Response::html(
            $app->view()->render('pages/styleguide')
        ));
    }
};
