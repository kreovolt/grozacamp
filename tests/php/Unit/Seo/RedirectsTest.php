<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Seo;

use Groza\Http\Request;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Перенаправления со старого сайта (спека §5.4): один переход, 301, точный Location. */
final class RedirectsTest extends TestCase
{
    private const SPEC = [
        '/program/' => '/booking/',
        '/faq/' => '/info/#faq',
        '/about/' => '/',
        '/o-lagere/' => '/',
        '/official_info/' => '/svedeniya/',
        '/glavnaya/dokumenty/' => '/svedeniya/ob-organizacii/#dokumenty',
        '/plan/' => '/svedeniya/deyatelnost/',
        '/pravila-prebyvaniya-v-lagere-groza/' => '/pravila/',
        '/programma-voenno-patrioticheskogo-lagerya-groza/' => '/svedeniya/ob-organizacii/#dokumenty',
        '/booking_form_borba/' => '/booking/',
        '/forma-bronirovaniya-sambo/' => '/booking/',
        '/let/' => '/booking/',
    ];

    /** @return array<string,array{string,string}> */
    public static function configured(): array
    {
        $cfg = require dirname(__DIR__, 4) . '/config/redirects.php';
        $out = [];
        foreach ($cfg['map'] as $from => $to) {
            $out[$from] = [$from, $to];
        }

        return $out;
    }

    public function test_config_has_exactly_the_spec_pairs(): void
    {
        $cfg = require dirname(__DIR__, 4) . '/config/redirects.php';
        $this->assertSame(self::SPEC, $cfg['map']);
        $this->assertSame(['/wp-admin/', '/wp-login.php', '/xmlrpc.php'], $cfg['gone']);
        $this->assertNotEmpty($cfg['legacyShiftSlugs']);
    }

    /** @dataProvider configured */
    public function test_each_spec_redirect(string $from, string $to): void
    {
        $res = TestApp::get(TestApp::make(), $from);

        $this->assertSame(301, $res->status());
        $this->assertSame($to, $res->header('Location'));
    }

    /** @dataProvider configured */
    public function test_redirect_works_without_trailing_slash_in_one_hop(string $from, string $to): void
    {
        $res = TestApp::get(TestApp::make(), rtrim($from, '/'));

        $this->assertSame(301, $res->status());
        $this->assertSame($to, $res->header('Location'));
    }

    /** @dataProvider configured */
    public function test_redirect_targets_are_live_pages(string $from, string $to): void
    {
        $app = TestApp::make();
        $path = explode('#', $to)[0];
        $this->assertSame(200, TestApp::get($app, $path)->status(), $to);
    }

    public function test_query_string_is_kept_before_fragment(): void
    {
        $app = TestApp::make();

        $res = TestApp::get($app, '/faq/', ['utm_source' => 'vk']);
        $this->assertSame('/info/?utm_source=vk#faq', $res->header('Location'));

        $res = TestApp::get($app, '/about/', ['a' => '1']);
        $this->assertSame('/?a=1', $res->header('Location'));
    }

    public function test_redirects_apply_to_get_and_head_only(): void
    {
        $app = TestApp::make();

        $this->assertSame(301, $app->handle(Request::create('HEAD', '/faq/'))->status());
        $post = $app->handle(Request::create('POST', '/faq/'));
        $this->assertNotSame(301, $post->status());
        $this->assertNull($post->header('Location'));
    }

    /** @dataProvider wp */
    public function test_wp_paths_404(string $path): void
    {
        $res = TestApp::get(TestApp::make(), $path);

        $this->assertSame(404, $res->status());
    }

    /** @return array<string,array{string}> */
    public static function wp(): array
    {
        return ['admin' => ['/wp-admin/'], 'admin file' => ['/wp-admin/admin-ajax.php'], 'login' => ['/wp-login.php'], 'xmlrpc' => ['/xmlrpc.php']];
    }

    public function test_wp_paths_404_for_post_too(): void
    {
        $this->assertSame(404, TestApp::post(TestApp::make(), '/xmlrpc.php', [])->status());
    }

    public function test_legacy_shift_slug_still_goes_to_booking(): void
    {
        $res = TestApp::get(TestApp::make(), '/program/shturmovik/');

        $this->assertSame(301, $res->status());
        $this->assertSame('/booking/', $res->header('Location'));
    }

    public function test_app_exposes_one_shared_redirects_loader(): void
    {
        $cfg = TestApp::make()->redirects();

        $this->assertArrayHasKey('map', $cfg);
        $this->assertArrayHasKey('gone', $cfg);
        $this->assertArrayHasKey('legacyShiftSlugs', $cfg);
    }

    public function test_www_host_redirects_to_canonical_origin_with_path_and_query(): void
    {
        $app = TestApp::make(['baseUrl' => 'https://grozacamp.ru']);

        $res = $app->handle(Request::create('GET', '/info/', ['x' => '1'], [], ['Host' => 'www.grozacamp.ru']));

        $this->assertSame(301, $res->status());
        $this->assertSame('https://grozacamp.ru/info/?x=1', $res->header('Location'));
    }

    public function test_canonical_and_unknown_hosts_are_not_redirected(): void
    {
        $app = TestApp::make(['baseUrl' => 'https://grozacamp.ru']);

        $this->assertSame(200, $app->handle(Request::create('GET', '/info/', [], [], ['Host' => 'grozacamp.ru']))->status());
        $this->assertSame(200, $app->handle(Request::create('GET', '/info/'))->status());
        $this->assertSame(200, $app->handle(Request::create('GET', '/info/', [], [], ['Host' => 'www.other.ru']))->status());
    }
}
