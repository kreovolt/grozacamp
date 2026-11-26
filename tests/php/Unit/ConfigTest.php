<?php
declare(strict_types=1);

namespace Groza\Tests\Unit;

use Groza\Http\Request;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * Без настроек сайт — боевой (env=prod), а не dev: потерянный config/local.php не выключает пароль тестового адреса,
 * Secure у cookie и SMTP. Не настроен — публичные страницы работают, формы показывают телефон, админка закрыта (503).
 */
final class ConfigTest extends TestCase
{
    private const SECRET = 'prod-secret-prod-secret-prod-secret-xx';

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** config/config.php в отдельном процессе с заданными GROZA_* (остальные GROZA_* убраны). @param array<string,string> $env @return array<string,mixed> */
    private static function configWith(array $env): array
    {
        $vars = array_filter(getenv(), static fn (string $k): bool => !str_starts_with($k, 'GROZA_'), ARRAY_FILTER_USE_KEY) + $env;
        $code = 'echo json_encode(require "config/config.php");';
        $p = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::root(), $vars);
        $out = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);

        return (array) json_decode($out, true);
    }

    private function skipWithLocalConfig(): void
    {
        if (is_file(self::root() . '/config/local.php')) {
            $this->markTestSkipped('есть config/local.php: умолчания на этой машине переопределены');
        }
    }

    public function test_default_env_is_prod_without_base_url(): void
    {
        $this->skipWithLocalConfig();
        $c = self::configWith([]);

        $this->assertSame('prod', $c['env']);
        $this->assertSame('', $c['baseUrl']);
        $this->assertSame([], $c['trustedProxies']);
    }

    public function test_dev_and_test_get_local_base_url_and_unknown_env_is_prod(): void
    {
        $this->skipWithLocalConfig();
        $this->assertSame(['dev', 'http://127.0.0.1:8080'], array_values(array_intersect_key(self::configWith(['GROZA_ENV' => 'dev']), ['env' => 1, 'baseUrl' => 1])));
        $this->assertSame('http://127.0.0.1:8080', self::configWith(['GROZA_ENV' => 'test'])['baseUrl']);
        $this->assertSame('prod', self::configWith(['GROZA_ENV' => 'production'])['env']);
        $this->assertSame(['10.0.0.5', '10.0.0.6'], self::configWith(['GROZA_TRUSTED_PROXIES' => ' 10.0.0.5, 10.0.0.6 ,'])['trustedProxies']);
    }

    public function test_dev_tooling_sets_env_explicitly(): void
    {
        $dev = (string) file_get_contents(self::root() . '/scripts/dev.mjs');
        $this->assertMatchesRegularExpression("/GROZA_ENV: process\\.env\\.GROZA_ENV \\|\\| 'dev'/", $dev);

        $pw = (string) file_get_contents(self::root() . '/playwright.config.ts');
        $this->assertSame(substr_count($pw, 'command:'), substr_count($pw, "GROZA_ENV: 'test'"), 'у каждого сервера e2e — GROZA_ENV=test');
        $this->assertGreaterThan(0, substr_count($pw, 'command:'));
    }

    /** @return array<string,array{0:array<string,mixed>,1:list<string>}> */
    public static function misconfigured(): array
    {
        return [
            'nothing' => [['baseUrl' => '', 'appSecret' => ''], ['baseUrl', 'appSecret']],
            'no secret' => [['baseUrl' => 'https://grozacamp.ru', 'appSecret' => ''], ['appSecret']],
            'example secret' => [['baseUrl' => 'https://grozacamp.ru', 'appSecret' => 'замените-на-длинную-случайную-строку'], ['appSecret']],
            'no base url' => [['baseUrl' => '', 'appSecret' => self::SECRET], ['baseUrl']],
            'http base url' => [['baseUrl' => 'http://grozacamp.ru', 'appSecret' => self::SECRET], ['baseUrl']],
            'base url with path' => [['baseUrl' => 'https://grozacamp.ru/site', 'appSecret' => self::SECRET], ['baseUrl']],
        ];
    }

    /**
     * @param array<string,mixed> $cfg
     * @param list<string> $keys
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('misconfigured')]
    public function test_misconfigured_prod_closes_admin_but_keeps_public_pages(array $cfg, array $keys): void
    {
        $app = TestApp::make(['env' => 'prod', 'fakeNow' => null] + $cfg);
        $problems = implode("\n", $app->configProblems());
        foreach ($keys as $k) {
            $this->assertStringContainsString($k, $problems);
        }
        $this->assertCount(count($keys), $app->configProblems());

        foreach ([['GET', '/admin/'], ['GET', '/admin/login/'], ['POST', '/admin/login/'], ['GET', '/admin/shifts/']] as [$method, $path]) {
            $res = $app->handle(Request::create($method, $path, [], ['login' => 'x', 'password' => 'y'], [], [], '127.0.0.1', true));
            $this->assertSame(503, $res->status(), "$method $path");
            $this->assertStringContainsString('Сайт не настроен: заполните config/local.php', $res->body());
            $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
            $this->assertSame('noindex, nofollow', $res->header('X-Robots-Tag'));
            $this->assertStringNotContainsString('name="password"', $res->body(), 'формы входа нет');
            $this->assertStringNotContainsString(self::SECRET, $res->body(), 'значения настроек не показываются');
        }

        $home = $app->handle(Request::create('GET', '/', https: true));
        $this->assertSame(200, $home->status());
        $booking = $app->handle(Request::create('GET', '/booking/', https: true));
        $this->assertSame(200, $booking->status());
        $this->assertStringNotContainsString('name="_ts"', $booking->body(), 'форма заменена телефоном');
        $this->assertStringContainsString('+7 (342) 247-43-47', $booking->body());
        $this->assertNull($app->guardFields('booking'));
    }

    public function test_staging_misconfig_is_reported_after_the_staging_password(): void
    {
        $app = TestApp::make(['env' => 'staging', 'baseUrl' => 'https://staging.grozacamp.ru', 'appSecret' => '', 'staging' => ['user' => 'u', 'pass' => 'p']]);
        $this->assertSame(401, $app->handle(Request::create('GET', '/admin/', https: true))->status());
        $res = $app->handle(Request::create('GET', '/admin/', [], [], ['Authorization' => 'Basic ' . base64_encode('u:p')], https: true));
        $this->assertSame(503, $res->status());
        $this->assertStringContainsString('appSecret', $res->body());
    }

    public function test_configured_prod_shows_login(): void
    {
        $app = TestApp::make(['env' => 'prod', 'baseUrl' => 'https://grozacamp.ru', 'appSecret' => self::SECRET, 'fakeNow' => null]);

        $this->assertSame([], $app->configProblems());
        $res = $app->handle(Request::create('GET', '/admin/login/', https: true));
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('name="password"', $res->body());
        $this->assertNotNull($app->guardFields('booking'));
    }

    public function test_dev_and_test_are_never_reported_as_misconfigured(): void
    {
        $this->assertSame([], TestApp::make(['env' => 'dev', 'appSecret' => '', 'baseUrl' => ''])->configProblems());
        $this->assertSame([], TestApp::make(['appSecret' => ''])->configProblems());
    }
}
