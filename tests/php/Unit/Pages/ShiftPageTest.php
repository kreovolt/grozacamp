<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class ShiftPageTest extends TestCase
{
    public function test_shift_page_renders(): void
    {
        $res = TestApp::get(TestApp::make(), '/program/spasatel-2027/');
        $this->assertSame(200, $res->status());
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Спасатель\s*</h1>#u', $res->body());
        $this->assertStringContainsString('href="/booking/?smena=spasatel-2027#zayavka"', $res->body());
        $this->assertStringContainsString('Поиск пострадавших вместе с сотрудниками МЧС', $res->body());
        $this->assertStringNotContainsString('noindex', $res->body());
    }

    public function test_shift_page_404_for_unknown(): void
    {
        $this->assertSame(404, TestApp::get(TestApp::make(), '/program/no-such-shift/')->status());
    }

    public function test_legacy_slug_redirects_to_booking(): void
    {
        $res = TestApp::get(TestApp::make(), '/program/leto-v-groze-1-smena-2/');
        $this->assertSame(301, $res->status());
        $this->assertSame('/booking/', $res->header('Location'));
    }

    public function test_past_shift_page(): void
    {
        $app = TestApp::make(['fakeNow' => '2027-09-01T12:00:00+05:00']);
        $res = TestApp::get($app, '/program/spasatel-2027/');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Смена завершена', $res->body());
        $this->assertStringContainsString('href="/booking/"', $res->body());
        $this->assertStringContainsString('noindex', $res->body());
        $this->assertStringNotContainsString('?smena=spasatel-2027', $res->body());
    }

    public function test_last_day_is_not_past(): void
    {
        $app = TestApp::make(['fakeNow' => '2027-07-22T23:30:00+05:00']);
        $this->assertStringNotContainsString('Смена завершена', TestApp::get($app, '/program/spasatel-2027/')->body());
    }

    public function test_price_missing_text(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('shifts')['items'];
        $items[0]['price'] = null;
        $app->store()->write('shifts', ['items' => $items], 'test');
        $body = TestApp::get($app, '/program/spasatel-2027/')->body();
        $this->assertStringContainsString('Стоимость уточняйте в офисе', $body);
        $this->assertStringContainsString('href="tel:+73422474347"', $body);
    }

    public function test_shift_page_shows_venue_and_included(): void
    {
        $body = TestApp::get(TestApp::make(), '/program/spasatel-2027/')->body();
        $this->assertStringContainsString('ДЗОЛ «Солнышко»', $body);
        $this->assertStringContainsString('Что входит в стоимость', $body);
        $this->assertStringContainsString('Трансфер из Перми в лагерь и обратно', $body);
        $this->assertStringContainsString('60', $body);
    }

    public function test_empty_activities_omits_list(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('shifts')['items'];
        $items[0]['activities'] = [];
        $app->store()->write('shifts', ['items' => $items], 'test');
        $this->assertStringNotContainsString('Чем займёмся', TestApp::get($app, '/program/spasatel-2027/')->body());
    }

    public function test_closed_shift_has_no_booking_button(): void
    {
        $body = TestApp::get(TestApp::make(), '/program/pogranichnik-2027/')->body();
        $this->assertStringContainsString('Мест нет', $body);
        $this->assertStringNotContainsString('?smena=pogranichnik-2027', $body);
    }

    public function test_soon_shift_links_to_booking_without_preselect(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('shifts')['items'];
        foreach ($items as &$s) {
            if (($s['slug'] ?? '') === 'vperyod-i-vverh-2027') {
                $s['hidden'] = false;
            }
        }
        unset($s);
        $app->store()->write('shifts', ['items' => $items], 'test');
        $body = TestApp::get($app, '/program/vperyod-i-vverh-2027/')->body();
        $this->assertStringContainsString('href="/booking/#zayavka"', $body);
        $this->assertStringContainsString('>Оставить заявку</span>', $body);
        $this->assertStringNotContainsString('?smena=vperyod-i-vverh-2027', $body);
        $this->assertStringNotContainsString('>Забронировать</span>', $body);
    }
}
