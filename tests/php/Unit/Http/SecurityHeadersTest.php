<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Http;

use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
use Groza\Http\SecurityHeaders;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    private const SECRET = 'prod-secret-prod-secret-prod-secret-xx';

    private function csp(Response $r): string
    {
        return (string) $r->header('Content-Security-Policy');
    }

    public function test_headers_on_every_response(): void
    {
        $app = TestApp::make(['env' => 'prod', 'appSecret' => self::SECRET]);
        $responses = [
            'page' => TestApp::get($app, '/'),
            '404' => TestApp::get($app, '/net-takoy/'),
            'redirect' => TestApp::get($app, '/faq/'),
            'slash redirect' => TestApp::get($app, '/booking'),
            'post' => TestApp::post($app, '/form/booking/', []),
            'robots' => TestApp::get($app, '/robots.txt'),
            'wp' => TestApp::get($app, '/wp-login.php'),
        ];

        foreach ($responses as $name => $r) {
            $csp = $this->csp($r);
            $this->assertStringContainsString("default-src 'self'", $csp, (string) $name);
            $this->assertStringContainsString("frame-ancestors 'none'", $csp, (string) $name);
            $this->assertSame('nosniff', $r->header('X-Content-Type-Options'), (string) $name);
            $this->assertSame('strict-origin-when-cross-origin', $r->header('Referrer-Policy'), (string) $name);
            $this->assertSame('DENY', $r->header('X-Frame-Options'), (string) $name);
            $this->assertNotNull($r->header('Permissions-Policy'), (string) $name);
            $this->assertNotNull($r->header('Strict-Transport-Security'), (string) $name);
        }
    }

    public function test_exact_csp_without_metrika(): void
    {
        $r = SecurityHeaders::apply(Response::html('x'), ['env' => 'prod', 'metrikaId' => '']);

        $this->assertSame(
            "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
            $this->csp($r)
        );
        $this->assertStringNotContainsString('unsafe', $this->csp($r));
    }

    public function test_hsts_only_in_prod(): void
    {
        foreach (['dev', 'test', 'staging'] as $env) {
            $this->assertNull(SecurityHeaders::apply(Response::html('x'), ['env' => $env, 'metrikaId' => ''])->header('Strict-Transport-Security'), $env);
        }
        $this->assertStringContainsString('max-age=', (string) SecurityHeaders::apply(Response::html('x'), ['env' => 'prod'])->header('Strict-Transport-Security'));
    }

    public function test_csp_allows_metrika_only_when_configured(): void
    {
        $off = $this->csp(SecurityHeaders::apply(Response::html('x'), ['env' => 'prod', 'metrikaId' => '']));
        $on = $this->csp(SecurityHeaders::apply(Response::html('x'), ['env' => 'prod', 'metrikaId' => '101518127']));

        $this->assertStringNotContainsString('yandex', $off);
        foreach (['script-src', 'img-src', 'connect-src'] as $directive) {
            $this->assertMatchesRegularExpression('#' . $directive . '[^;]*https://mc\.yandex\.ru[^;]*https://mc\.yandex\.com#', $on, $directive);
        }
        $this->assertStringNotContainsString('unsafe', $on);
        $this->assertStringContainsString("frame-ancestors 'none'", $on);
    }

    public function test_staging_adds_robots_header(): void
    {
        $r = SecurityHeaders::apply(Response::html('x'), ['env' => 'staging']);
        $this->assertSame('noindex, nofollow', $r->header('X-Robots-Tag'));
        $this->assertNull(SecurityHeaders::apply(Response::html('x'), ['env' => 'prod'])->header('X-Robots-Tag'));
    }

    public function test_admin_paths_are_noindex(): void
    {
        $app = TestApp::make(['env' => 'prod', 'appSecret' => self::SECRET]);
        $this->assertSame('noindex, nofollow', TestApp::get($app, '/admin/')->header('X-Robots-Tag'));
    }

    public function test_staging_requires_basic_auth(): void
    {
        $app = TestApp::make(['env' => 'staging', 'staging' => ['user' => 'camp', 'pass' => 'pw'], 'appSecret' => self::SECRET]);

        $no = TestApp::get($app, '/');
        $this->assertSame(401, $no->status());
        $this->assertStringStartsWith('Basic realm=', (string) $no->header('WWW-Authenticate'));
        $this->assertSame('noindex, nofollow', $no->header('X-Robots-Tag'));
        $this->assertNotNull($no->header('Content-Security-Policy'));

        $bad = $app->handle(Request::create('GET', '/', [], [], ['Authorization' => 'Basic ' . base64_encode('camp:wrong')]));
        $this->assertSame(401, $bad->status());

        $ok = $app->handle(Request::create('GET', '/', [], [], ['Authorization' => 'Basic ' . base64_encode('camp:pw')]));
        $this->assertSame(200, $ok->status());
        $this->assertSame('noindex, nofollow', $ok->header('X-Robots-Tag'));
    }

    public function test_staging_without_configured_credentials_is_closed(): void
    {
        $app = TestApp::make(['env' => 'staging', 'staging' => ['user' => '', 'pass' => ''], 'appSecret' => self::SECRET]);

        $this->assertSame(401, TestApp::get($app, '/')->status());
        $empty = $app->handle(Request::create('GET', '/', [], [], ['Authorization' => 'Basic ' . base64_encode(':')]));
        $this->assertSame(401, $empty->status());
    }

    public function test_cookie_notice_only_with_metrika(): void
    {
        $without = TestApp::get(TestApp::make(), '/')->body();
        $this->assertStringNotContainsString('data-cookie-notice', $without);
        $this->assertStringNotContainsString('yandex', $without);
        $this->assertStringNotContainsString('metrika.js', $without);

        $with = TestApp::get(TestApp::make(['metrikaId' => '101518127']), '/')->body();
        $this->assertStringContainsString('data-cookie-notice', $with);
        $this->assertMatchesRegularExpression('#data-cookie-notice.*href="/policy/".*Понятно#us', $with);
        $this->assertMatchesRegularExpression('#<script src="/assets/js/metrika\.js[^"]*" data-id="101518127" defer></script>#', $with);
        $this->assertStringNotContainsString('<script>', $with);
    }

    public function test_metrika_id_from_settings_and_validated(): void
    {
        $app = TestApp::make();
        $app->store()->write('settings', ['metrikaId' => '"><script>'], 'test');
        $this->assertStringNotContainsString('data-cookie-notice', TestApp::get($app, '/')->body());

        $app->store()->write('settings', ['metrikaId' => '55555'], 'test');
        $r = TestApp::get($app, '/');
        $this->assertStringContainsString('data-id="55555"', $r->body());
        $this->assertStringContainsString('mc.yandex.ru', $this->csp($r));
    }

    public function test_no_inline_script_or_style_in_pages(): void
    {
        $app = TestApp::make(['metrikaId' => '101518127']);
        foreach (['/', '/booking/', '/info/', '/program/spasatel-2027/', '/svedeniya/mto/', '/contacts/', '/vacancies/', '/team/', '/404x/'] as $path) {
            $html = TestApp::get($app, $path)->body();
            $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*\bsrc=)(?![^>]*application/ld\+json)#i', $html, $path);
            $this->assertDoesNotMatchRegularExpression('#\sstyle\s*=#i', $html, $path);
            $this->assertDoesNotMatchRegularExpression('#<style#i', $html, $path);
            $this->assertDoesNotMatchRegularExpression('#\son[a-z]+\s*=\s*["\']#i', $html, $path);
        }
    }

    public function test_router_regex_does_not_match_before_trailing_newline(): void
    {
        $router = new Router();
        $router->get('/booking/', static fn (): Response => Response::html('ok'));

        $this->assertSame(404, $router->dispatch(Request::create('GET', "/booking/\n"))->status());
    }

    public function test_htaccess_hardening(): void
    {
        $ht = (string) file_get_contents(dirname(__DIR__, 4) . '/public/.htaccess');

        $this->assertStringContainsString('.well-known/acme-challenge/', $ht);
        $this->assertStringContainsString('[F,NC,L]', $ht);
        $this->assertMatchesRegularExpression('#RedirectMatch 404#', $ht);
        foreach (['composer', 'package', 'phpunit', 'playwright', 'tailwind', 'md'] as $word) {
            $this->assertStringContainsString($word, $ht);
        }
        $this->assertStringContainsString('max-age=31536000, immutable', $ht);
        $this->assertStringContainsString('HTTP_AUTHORIZATION', $ht);
    }
}
