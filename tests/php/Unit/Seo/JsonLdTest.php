<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Seo;

use Groza\Seo\JsonLd;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class JsonLdTest extends TestCase
{
    private const BASE = 'https://grozacamp.ru';

    public function test_camp_jsonld(): void
    {
        $ld = JsonLd::camp(TestApp::make()->settings(), self::BASE);

        $this->assertSame('https://schema.org', $ld['@context']);
        $this->assertContains('SummerCamp', (array) $ld['@type']);
        $this->assertSame('+7 (342) 247-43-47', $ld['telephone']);
        $this->assertContains('https://vk.com/grozacamp', $ld['sameAs']);
        $this->assertSame('Пермь', $ld['address']['addressLocality']);
        $this->assertStringContainsString('Милиционера Власова, 37', $ld['address']['streetAddress']);
        $this->assertSame(self::BASE . '/', $ld['url']);
    }

    public function test_event_jsonld_offer(): void
    {
        $shift = ['slug' => 'spasatel', 'title' => 'Спасатель', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22', 'price' => 60000, 'ageMin' => 8, 'ageMax' => 17, 'lead' => 'Смена про спасение.'];
        $venue = ['name' => 'ДЗОЛ «Солнышко»', 'address' => 'дер. Коляды, Пермский край'];

        $ld = JsonLd::event($shift, $venue, self::BASE);

        $this->assertSame('Event', $ld['@type']);
        $this->assertSame('2027-07-02', $ld['startDate']);
        $this->assertSame('2027-07-22', $ld['endDate']);
        $this->assertSame('60000', $ld['offers']['price']);
        $this->assertSame('RUB', $ld['offers']['priceCurrency']);
        $this->assertSame(self::BASE . '/program/spasatel/', $ld['offers']['url']);
        $this->assertSame('ДЗОЛ «Солнышко»', $ld['location']['name']);
    }

    public function test_event_without_price_has_no_offers_and_without_venue_still_has_location(): void
    {
        $ld = JsonLd::event(['slug' => 's', 'title' => 'С', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22'], null, self::BASE);

        $this->assertArrayNotHasKey('offers', $ld);
        $this->assertArrayHasKey('location', $ld);
    }

    public function test_encode_escapes_script_breakout(): void
    {
        $json = JsonLd::encode(['name' => '</script><script>alert(1)</script>', 'x' => 'Привет']);

        $this->assertStringNotContainsString('</', $json);
        $this->assertStringContainsString('Привет', $json);
        $this->assertSame('</script><script>alert(1)</script>', json_decode($json, true)['name']);
    }

    public function test_pages_embed_jsonld(): void
    {
        $app = TestApp::make();

        $home = TestApp::get($app, '/')->body();
        $this->assertStringContainsString('<script type="application/ld+json">', $home);
        $this->assertStringContainsString('"SummerCamp"', $home);

        $shift = TestApp::get($app, '/program/spasatel-2027/')->body();
        $this->assertStringContainsString('"@type":"Event"', $shift);
        $this->assertStringContainsString('"priceCurrency":"RUB"', $shift);
    }
}
