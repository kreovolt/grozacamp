<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Http;

use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function ok(): callable
    {
        return static fn (Request $r, array $p): Response => Response::html('ok');
    }

    public function test_get_route(): void
    {
        $router = new Router();
        $router->get('/booking/', $this->ok());

        $res = $router->dispatch(Request::create('GET', '/booking/'));

        $this->assertSame(200, $res->status());
        $this->assertSame('ok', $res->body());
    }

    public function test_gone_paths_match_regardless_of_case(): void
    {
        $router = new Router();
        $router->gone(['/wp-admin/', '/wp-login.php']);
        $router->get('/{x}/', $this->ok());

        foreach (['/wp-admin/', '/WP-Admin/', '/wp-admin/x/y', '/WP-LOGIN.PHP', '/Wp-Login.php'] as $path) {
            $this->assertSame(404, $router->dispatch(Request::create('GET', $path))->status(), $path);
        }
        $this->assertSame(200, $router->dispatch(Request::create('GET', '/wp-adminx/'))->status());
    }

    public function test_missing_trailing_slash_redirects(): void
    {
        $router = new Router();
        $router->get('/booking/', $this->ok());

        $res = $router->dispatch(Request::create('GET', '/booking'));

        $this->assertSame(301, $res->status());
        $this->assertSame('/booking/', $res->header('Location'));
    }

    public function test_param_route(): void
    {
        $seen = null;
        $router = new Router();
        $router->get('/program/{slug}/', function (Request $r, array $params) use (&$seen): Response {
            $seen = $params;
            return Response::html('ok');
        });

        $res = $router->dispatch(Request::create('GET', '/program/spasatel/'));

        $this->assertSame(200, $res->status());
        $this->assertSame('spasatel', $seen['slug']);
    }

    public function test_bad_param_is_404(): void
    {
        $router = new Router();
        $router->get('/program/{slug}/', $this->ok());

        $res = $router->dispatch(Request::create('GET', '/program/Спасатель/'));

        $this->assertSame(404, $res->status());
    }

    public function test_unknown_path_404(): void
    {
        $router = new Router();
        $router->get('/booking/', $this->ok());

        $this->assertSame(404, $router->dispatch(Request::create('GET', '/nope/'))->status());
    }

    public function test_redirect_map_single_hop(): void
    {
        $router = new Router();
        $router->get('/info/', $this->ok());
        $router->redirects(['/faq/' => '/info/#faq']);

        $res = $router->dispatch(Request::create('GET', '/faq'));

        $this->assertSame(301, $res->status());
        $this->assertSame('/info/#faq', $res->header('Location'));
    }

    public function test_method_not_allowed(): void
    {
        $router = new Router();
        $router->get('/booking/', $this->ok());

        $res = $router->dispatch(Request::create('POST', '/booking/'));

        $this->assertSame(405, $res->status());
        $this->assertSame('GET', $res->header('Allow'));
    }
}
