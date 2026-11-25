<?php
declare(strict_types=1);

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
use Groza\Pages\BookingPage;
use Groza\Pages\ContactsPage;
use Groza\Pages\FormController;
use Groza\Pages\HomePage;
use Groza\Pages\InfoPage;
use Groza\Pages\NotFoundPage;
use Groza\Pages\RobotsController;
use Groza\Pages\SitemapController;
use Groza\Pages\ShiftPage;
use Groza\Pages\SvedeniyaPage;
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
    $router->get('/svedeniya/', SvedeniyaPage::hub($app));
    $router->get('/svedeniya/ob-organizacii/', SvedeniyaPage::obOrganizacii($app));
    $router->get('/svedeniya/deyatelnost/', SvedeniyaPage::deyatelnost($app));
    $router->get('/svedeniya/mto/', SvedeniyaPage::mto($app));
    $router->get('/svedeniya/uslugi/', SvedeniyaPage::uslugi($app));
    $router->get('/svedeniya/dostupnaya-sreda/', SvedeniyaPage::dostupnaya($app));
    $router->get('/svedeniya/inaya-informaciya/', SvedeniyaPage::inaya($app));
    $router->get('/robots.txt', new RobotsController($app));
    $router->get('/sitemap.xml', new SitemapController($app));
    $router->notFound(new NotFoundPage($app));

    $forms = new FormController($app);
    $router->post('/form/booking/', [$forms, 'booking']);
    $router->post('/form/vacancy/', [$forms, 'vacancy']);
    $router->get('/spasibo/', [$forms, 'thanks']);

    // Витрина дизайн-системы для разработки (не для посетителей): /_styleguide/ только в dev и test.
    if (in_array($app->config()['env'], ['dev', 'test'], true)) {
        $router->get('/_styleguide/', static fn (Request $r, array $params): Response => Response::html(
            $app->view()->render('pages/styleguide')
        ));
    }
};
