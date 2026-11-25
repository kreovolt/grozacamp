<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Seo;

use DateTimeImmutable;
use Groza\Http\Request;
use Groza\Seo\Sitemap;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class SitemapTest extends TestCase
{
    private const BASE = 'https://grozacamp.ru';

    public function test_sitemap_contains_public_pages_and_visible_shifts(): void
    {
        $app = TestApp::make(['baseUrl' => self::BASE, 'env' => 'prod', 'appSecret' => str_repeat('s', 40)]);
        $res = TestApp::get($app, '/sitemap.xml');
        $xml = $res->body();

        $this->assertSame(200, $res->status());
        $this->assertStringStartsWith('application/xml', (string) $res->header('Content-Type'));
        $this->assertNotFalse(simplexml_load_string($xml), 'валидный XML');
        foreach (['/', '/booking/', '/svedeniya/mto/', '/svedeniya/', '/info/', '/program/spasatel-2027/', '/policy/', '/pravila/'] as $path) {
            $this->assertStringContainsString('<loc>' . self::BASE . $path . '</loc>', $xml, $path);
        }
        foreach (['/admin/', '/spasibo/', '/form/', '/robots.txt', '/_styleguide/'] as $path) {
            $this->assertStringNotContainsString(self::BASE . $path, $xml, $path);
        }
        $this->assertMatchesRegularExpression('#<lastmod>\d{4}-\d{2}-\d{2}</lastmod>#', $xml);
    }

    public function test_hidden_and_past_shifts_are_left_out(): void
    {
        $shifts = [
            ['slug' => 'old', 'dateStart' => '2020-06-01', 'dateEnd' => '2020-06-20'],
            ['slug' => 'secret', 'dateStart' => '2027-06-01', 'dateEnd' => '2027-06-20', 'hidden' => true],
            ['slug' => 'live', 'dateStart' => '2027-06-01', 'dateEnd' => '2027-06-20'],
            ['slug' => 'last-day', 'dateStart' => '2027-01-01', 'dateEnd' => '2027-01-15'],
        ];
        $xml = Sitemap::build(['pages' => [['path' => '/']], 'shifts' => $shifts], self::BASE, new DateTimeImmutable('2027-01-15 12:00', new \DateTimeZone('Asia/Yekaterinburg')));

        $this->assertStringContainsString('/program/live/', $xml);
        $this->assertStringContainsString('/program/last-day/', $xml);
        $this->assertStringNotContainsString('/program/old/', $xml);
        $this->assertStringNotContainsString('/program/secret/', $xml);
    }

    public function test_robots_prod_and_staging(): void
    {
        $prod = TestApp::make(['baseUrl' => self::BASE, 'env' => 'prod', 'appSecret' => str_repeat('s', 40)]);
        $res = TestApp::get($prod, '/robots.txt');
        $this->assertSame(200, $res->status());
        $this->assertStringStartsWith('text/plain', (string) $res->header('Content-Type'));
        $this->assertStringContainsString('Disallow: /admin/', $res->body());
        $this->assertStringContainsString('Sitemap: ' . self::BASE . '/sitemap.xml', $res->body());
        $this->assertStringNotContainsString("Disallow: /\n", $res->body());

        $staging = TestApp::make(['baseUrl' => self::BASE, 'env' => 'staging', 'staging' => ['user' => 'u', 'pass' => 'p'], 'appSecret' => str_repeat('s', 40)]);
        $req = Request::create('GET', '/robots.txt', [], [], ['Authorization' => 'Basic ' . base64_encode('u:p')]);
        $body = $staging->handle($req)->body();
        $this->assertStringContainsString("Disallow: /\n", $body . "\n");
        $this->assertStringNotContainsString('Sitemap:', $body);
    }
}
