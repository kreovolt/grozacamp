<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Http;

use Groza\App;
use Groza\Http\Request;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Только HTTPS (спека §10.5): на staging и prod запрос по http — 301 на https-адрес из baseUrl. */
final class HttpsRedirectTest extends TestCase
{
    private const BASE = 'https://grozacamp.ru';
    private const SECRET = 'prod-secret-prod-secret-prod-secret-xx';

    /** @param array<string,mixed> $extra */
    private function app(string $env = 'prod', array $extra = []): App
    {
        return TestApp::make(['env' => $env, 'baseUrl' => self::BASE, 'appSecret' => self::SECRET, 'fakeNow' => null] + $extra);
    }

    /** @param array<string,string> $headers @param array<string,mixed> $query */
    private static function req(string $path, bool $https, array $headers = [], string $ip = '198.51.100.7', string $method = 'GET', array $query = []): Request
    {
        return Request::create($method, $path, $query, [], $headers + ['Host' => 'grozacamp.ru'], [], $ip, $https);
    }

    public function test_plain_http_in_prod_goes_to_https_with_same_path_and_query(): void
    {
        $res = $this->app()->handle(self::req('/booking/', false, query: ['smena' => 'spasatel-2027']));

        $this->assertSame(301, $res->status());
        $this->assertSame(self::BASE . '/booking/?smena=spasatel-2027', $res->header('Location'));
        $this->assertNotNull($res->header('Content-Security-Policy'), 'заголовки безопасности и на переадресации');
    }

    public function test_path_is_reencoded_and_host_header_is_not_reflected(): void
    {
        $res = $this->app()->handle(self::req('/program/смена/', false, ['Host' => 'evil.example']));

        $this->assertSame(301, $res->status());
        $this->assertSame(self::BASE . '/program/' . rawurlencode('смена') . '/', $res->header('Location'));
    }

    public function test_https_request_is_served(): void
    {
        $this->assertSame(200, $this->app()->handle(self::req('/booking/', true))->status());
    }

    public function test_admin_over_http_is_redirected_before_login_and_session(): void
    {
        $res = $this->app()->handle(self::req('/admin/login/', false, method: 'POST'));

        $this->assertSame(301, $res->status());
        $this->assertSame(self::BASE . '/admin/login/', $res->header('Location'));
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        $this->assertNull($res->header('Set-Cookie'));
    }

    public function test_staging_redirects_to_https_before_asking_for_the_password(): void
    {
        $app = $this->app('staging', ['staging' => ['user' => 'camp', 'pass' => 'pw']]);
        $plain = $app->handle(self::req('/', false));
        $this->assertSame(301, $plain->status());
        $this->assertNull($plain->header('WWW-Authenticate'), 'пароль тестового адреса по http не спрашивается');
        $this->assertSame('noindex, nofollow', $plain->header('X-Robots-Tag'));

        $secure = $app->handle(self::req('/', true));
        $this->assertSame(401, $secure->status());
        $this->assertNotNull($secure->header('WWW-Authenticate'));
    }

    public function test_forwarded_proto_is_trusted_only_from_configured_proxies(): void
    {
        $untrusted = $this->app();
        $this->assertSame(301, $untrusted->handle(self::req('/', false, ['X-Forwarded-Proto' => 'https']))->status(), 'чужой адрес не может назваться прокси');

        $app = $this->app('prod', ['trustedProxies' => ['10.0.0.5']]);
        $this->assertSame(200, $app->handle(self::req('/', false, ['X-Forwarded-Proto' => 'https'], '10.0.0.5'))->status());
        $this->assertSame(301, $app->handle(self::req('/', false, ['X-Forwarded-Proto' => 'http'], '10.0.0.5'))->status());
        $this->assertSame(301, $app->handle(self::req('/', false, [], '10.0.0.5'))->status());
        // список значений: решает последнее — его поставил ближайший (доверенный) прокси
        $this->assertSame(301, $app->handle(self::req('/', false, ['X-Forwarded-Proto' => 'https, http'], '10.0.0.5'))->status());
        $this->assertSame(301, $app->handle(self::req('/', false, ['X-Forwarded-Proto' => 'https'], '10.0.0.6'))->status());
    }

    public function test_dev_and_test_are_not_redirected(): void
    {
        foreach (['dev', 'test'] as $env) {
            $app = TestApp::make(['env' => $env]);
            $this->assertSame(200, $app->handle(Request::create('GET', '/'))->status(), $env);
        }
    }

    public function test_without_https_base_url_there_is_nowhere_to_redirect(): void
    {
        $app = TestApp::make(['env' => 'prod', 'baseUrl' => 'http://grozacamp.ru', 'appSecret' => self::SECRET, 'fakeNow' => null]);

        $this->assertSame(200, $app->handle(self::req('/', false))->status());
    }

    public function test_hsts_only_in_prod(): void
    {
        $this->assertNotNull($this->app()->handle(self::req('/', true))->header('Strict-Transport-Security'));
        $staging = $this->app('staging', ['staging' => ['user' => 'u', 'pass' => 'p']]);
        $res = $staging->handle(self::req('/', true, ['Authorization' => 'Basic ' . base64_encode('u:p')]));
        $this->assertSame(200, $res->status());
        $this->assertNull($res->header('Strict-Transport-Security'));
    }

    public function test_request_from_globals_detects_https(): void
    {
        $saved = $_SERVER;
        try {
            $cases = [
                [['HTTPS' => 'on'], true],
                [['HTTPS' => '1'], true],
                [['HTTPS' => 'off'], false],
                [['HTTPS' => ''], false],
                [['REQUEST_SCHEME' => 'https'], true],
                [['REQUEST_SCHEME' => 'http'], false],
                [['HTTP_X_FORWARDED_PROTO' => 'https'], false], // заголовок прокси решает App::isSecure, не Request
            ];
            foreach ($cases as [$server, $expected]) {
                $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'] + $server;
                $this->assertSame($expected, Request::fromGlobals()->isHttps(), json_encode($server));
            }
        } finally {
            $_SERVER = $saved;
        }
    }
}
