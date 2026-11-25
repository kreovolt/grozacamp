<?php
declare(strict_types=1);

use Groza\App;
use Groza\Content\Nav;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
use Groza\Pages\BookingPage;
use Groza\Pages\ContactsPage;
use Groza\Pages\FormController;
use Groza\Pages\HomePage;
use Groza\Pages\InfoPage;
use Groza\Pages\NotFoundPage;
use Groza\Pages\ShiftPage;
use Groza\Pages\TeamPage;
use Groza\Pages\TextPage;
use Groza\Pages\VacanciesPage;

return static function (Router $router, App $app): void {
    $router->get('/', new HomePage($app));
    $router->get('/booking/', new BookingPage($app));
    $router->get('/program/{slug}/', new ShiftPage($app));
    $router->get('/info/', new InfoPage($app));
    $router->get('/vacancies/', new VacanciesPage($app));
    $router->get('/team/', new TeamPage($app));
    $router->get('/contacts/', new ContactsPage($app));
    $router->get('/pravila/', TextPage::rules($app));
    $router->get('/policy/', TextPage::policy($app));
    $router->notFound(new NotFoundPage($app));

    $forms = new FormController($app);
    $router->post('/form/booking/', [$forms, 'booking']);
    $router->post('/form/vacancy/', [$forms, 'vacancy']);
    $router->get('/spasibo/', [$forms, 'thanks']);

    // ВРЕМЕННЫЕ ЗАГЛУШКИ (Task 4), чтобы пункты меню не вели в 404. Остались /svedeniya/ и подстраницы — их заменит Task 11.
    $home = ['label' => 'Главная', 'href' => '/'];
    $placeholder = static fn (string $title, array $crumbs): callable =>
        static fn (Request $r, array $params): Response => Response::html(
            $app->view()->render('pages/placeholder', ['title' => $title, 'crumbs' => $crumbs])
        );
    foreach (Nav::main() as $item) {
        if (!str_starts_with($item['href'], '/svedeniya/')) {
            continue; // настоящие страницы выше
        }
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
