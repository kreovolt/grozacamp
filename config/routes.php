<?php
declare(strict_types=1);

use Groza\Admin\Controllers\AuthController;
use Groza\Admin\Controllers\CollectionController;
use Groza\Admin\Controllers\DashboardController;
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

    $auth = new AuthController($app);
    $router->get('/admin/', [new DashboardController($app), 'index']);
    $router->get('/admin/login/', [$auth, 'showLogin']);
    $router->post('/admin/login/', [$auth, 'login']);
    $router->post('/admin/logout/', [$auth, 'logout']);
    // Выход — только POST; GET отвечает 405 (иначе его перехватил бы общий маршрут /admin/{collection}/ ниже)
    $router->get('/admin/logout/', static fn (Request $r, array $params): Response => Response::html('Method Not Allowed', 405)->withHeader('Allow', 'POST'));
    $router->get('/admin/password/', [$auth, 'showPassword']);
    $router->post('/admin/password/', [$auth, 'changePassword']);

    // Редактор списков. Порядок важен: сначала точные адреса (статус смены, «new», действия над записью),
    // потом общий /admin/{collection}/{id}/. ВСЕ маршруты админки с фиксированным первым сегментом
    // (/admin/settings/, а также двухсегментные вроде /admin/texts/{group}/ и /admin/history/{name}/)
    // регистрируются ВЫШЕ этого блока, иначе их перехватит {collection}/{id}.
    $collections = new CollectionController($app);
    $router->post('/admin/shifts/{id}/status/', [$collections, 'status']);
    $router->get('/admin/{collection}/', [$collections, 'list']);
    $router->get('/admin/{collection}/new/', [$collections, 'showNew']);
    $router->get('/admin/{collection}/{id}/delete/', [$collections, 'confirmDelete']);
    $router->post('/admin/{collection}/{id}/delete/', [$collections, 'delete']);
    $router->post('/admin/{collection}/{id}/move/', [$collections, 'move']);
    $router->get('/admin/{collection}/{id}/', [$collections, 'show']);
    $router->post('/admin/{collection}/{id}/', [$collections, 'save']);

    // Витрина дизайн-системы для разработки (не для посетителей): /_styleguide/ только в dev и test.
    if (in_array($app->config()['env'], ['dev', 'test'], true)) {
        $router->get('/_styleguide/', static fn (Request $r, array $params): Response => Response::html(
            $app->view()->render('pages/styleguide')
        ));
    }
};
