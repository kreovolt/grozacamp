<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Seo;

use Groza\Seo\Meta;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class MetaTest extends TestCase
{
    private const BASE = 'https://grozacamp.ru';

    /** @return list<string> */
    private static function publicPaths(): array
    {
        return ['/', '/booking/', '/program/spasatel-2027/', '/info/', '/vacancies/', '/team/', '/contacts/', '/pravila/', '/policy/',
            '/svedeniya/', '/svedeniya/ob-organizacii/', '/svedeniya/deyatelnost/', '/svedeniya/mto/', '/svedeniya/uslugi/',
            '/svedeniya/dostupnaya-sreda/', '/svedeniya/inaya-informaciya/'];
    }

    public function test_title_pattern_and_home_title(): void
    {
        $m = Meta::for('booking', ['baseUrl' => self::BASE, 'path' => '/booking/']);
        $this->assertSame('Купить путёвку — детский лагерь «Гроза», Пермь', $m['title']);

        $home = Meta::for('home', ['baseUrl' => self::BASE, 'path' => '/']);
        $this->assertSame('Детский лагерь «Гроза» — лагерь приключений под Пермью', $home['title']);

        $custom = Meta::for('shift', ['baseUrl' => self::BASE, 'path' => '/program/x/', 'title' => 'Спасатель']);
        $this->assertSame('Спасатель — детский лагерь «Гроза», Пермь', $custom['title']);
    }

    public function test_canonical_and_image_are_absolute(): void
    {
        $m = Meta::for('booking', ['baseUrl' => self::BASE . '/', 'path' => '/booking/']);

        $this->assertSame('https://grozacamp.ru/booking/', $m['canonical']);
        $this->assertSame('https://grozacamp.ru/assets/og-default.jpg', $m['image']);
        $this->assertFileExists(dirname(__DIR__, 4) . '/public/assets/og-default.jpg');
    }

    public function test_description_is_trimmed_to_160_on_a_word_boundary(): void
    {
        $long = str_repeat('Слово ', 60);
        $m = Meta::for('text', ['baseUrl' => self::BASE, 'path' => '/x/', 'title' => 'Х', 'description' => $long]);

        $this->assertLessThanOrEqual(160, mb_strlen($m['description']));
        $this->assertStringEndsWith('…', $m['description']);
        $this->assertStringNotContainsString('  ', $m['description']);
    }

    public function test_every_public_page_has_unique_title_and_good_description(): void
    {
        $app = TestApp::make(['baseUrl' => self::BASE]);
        $titles = [];
        foreach (self::publicPaths() as $path) {
            $res = TestApp::get($app, $path);
            $this->assertSame(200, $res->status(), $path);
            $html = $res->body();
            $this->assertSame(1, preg_match('#<title>([^<]+)</title>#u', $html, $t), $path);
            $titles[$path] = html_entity_decode($t[1]);
            $this->assertSame(1, preg_match('#<meta name="description" content="([^"]+)"#u', $html, $d), $path . ' description');
            $desc = html_entity_decode($d[1]);
            $this->assertGreaterThanOrEqual(40, mb_strlen($desc), $path);
            $this->assertLessThanOrEqual(160, mb_strlen($desc), $path);
            $this->assertStringContainsString('<link rel="canonical" href="' . self::BASE . $path . '">', $html, $path);
            $this->assertStringContainsString('<meta property="og:image" content="' . self::BASE . '/assets/og-default.jpg">', $html, $path);
        }
        $this->assertSame(count($titles), count(array_unique($titles)), 'титулы уникальны: ' . print_r($titles, true));
    }

    public function test_noindex_pages(): void
    {
        $app = TestApp::make(['baseUrl' => self::BASE]);

        foreach (['/spasibo/', '/net-takoy-stranicy/'] as $path) {
            $html = TestApp::get($app, $path)->body();
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html, $path);
            $this->assertStringNotContainsString('rel="canonical"', $html, $path);
        }
        $this->assertStringNotContainsString('name="robots"', TestApp::get($app, '/booking/')->body());
    }

    public function test_past_shift_is_noindex_and_staging_is_noindex(): void
    {
        $app = TestApp::make(['baseUrl' => self::BASE, 'fakeNow' => '2030-01-01T12:00:00+05:00']);
        $html = TestApp::get($app, '/program/spasatel-2027/')->body();
        $this->assertStringContainsString('name="robots" content="noindex', $html);

        $staging = TestApp::make(['env' => 'staging', 'staging' => ['user' => 'u', 'pass' => 'p'], 'appSecret' => str_repeat('s', 40)]);
        $req = \Groza\Http\Request::create('GET', '/booking/', [], [], ['Authorization' => 'Basic ' . base64_encode('u:p')]);
        $this->assertStringContainsString('name="robots" content="noindex', $staging->handle($req)->body());
    }
}
