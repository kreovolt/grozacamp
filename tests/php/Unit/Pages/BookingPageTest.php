<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\Http\Request;
use Groza\Pages\BookingPage;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** «Купить путёвку» (спека §7.2): смены из seed/demo, часы приложения — 2027-01-15. */
final class BookingPageTest extends TestCase
{
    public function test_lists_only_visible_shifts(): void
    {
        $res = TestApp::get(TestApp::make(), '/booking/');
        $this->assertSame(200, $res->status());
        $this->assertSame(3, substr_count($res->body(), 'class="shift-card'));
        $this->assertStringContainsString('id="smeny"', $res->body());
        $this->assertStringContainsString('id="zayavka"', $res->body());
        $this->assertStringNotContainsString('Вперёд и вверх', $res->body(), 'скрытая смена не показывается');
        $this->assertStringContainsString('href="/booking/?smena=spasatel-2027#zayavka"', $res->body());
        $this->assertStringContainsString('<h1', $res->body());
    }

    public function test_closed_shift_has_no_booking_button(): void
    {
        $body = TestApp::get(TestApp::make(), '/booking/')->body();
        $this->assertStringContainsString('Мест нет', $body);
        $this->assertStringNotContainsString('smena=pogranichnik-2027', $body);
        $this->assertSame(2, substr_count($body, '>Забронировать</span>'));
    }

    public function test_query_preselects_shift(): void
    {
        $res = TestApp::get(TestApp::make(), '/booking/', ['smena' => 'spasatel-2027']);
        $this->assertMatchesRegularExpression('#<option value="spasatel-2027" selected[ >]#', $res->body());
    }

    public function test_unknown_query_ignored(): void
    {
        foreach (['nope', 'pogranichnik-2027', '"><script>alert(1)</script>'] as $bad) {
            $res = TestApp::get(TestApp::make(), '/booking/', ['smena' => $bad]);
            $this->assertSame(200, $res->status());
            $this->assertDoesNotMatchRegularExpression('#<option value="[^"]+" selected#', $res->body());
            $this->assertStringNotContainsString('<script>alert', $res->body());
        }
    }

    public function test_any_option_label(): void
    {
        $this->assertStringContainsString('<option value="any">Пока не знаю / любая</option>', TestApp::get(TestApp::make(), '/booking/')->body());
    }

    public function test_no_shifts_shows_soon_text(): void
    {
        $app = TestApp::make();
        $app->store()->write('shifts', ['items' => []], 'test');
        $body = TestApp::get($app, '/booking/')->body();

        $this->assertStringContainsString('Даты скоро. Оставьте заявку — позвоним первыми', $body);
        $this->assertSame(0, substr_count($body, 'class="shift-card'));
        $this->assertSame(['any'], $this->optionValues($body));
    }

    public function test_call_and_whatsapp_buttons_and_included(): void
    {
        $body = TestApp::get(TestApp::make(), '/booking/')->body();
        $this->assertStringContainsString('href="tel:+73422474347"', $body);
        $this->assertStringContainsString('href="https://wa.me/79991239300"', $body);
        $this->assertStringContainsString('Что входит в стоимость', $body);
        $this->assertStringContainsString('href="/svedeniya/uslugi/"', $body);
    }

    public function test_validation_errors_rerender_booking_page(): void
    {
        $app = TestApp::make();
        $res = (new BookingPage($app))->render(Request::create('POST', '/form/booking/'), ['parentName' => 'Анна'], ['phone' => 'Укажите телефон'], 422);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('value="Анна"', $res->body());
        $this->assertStringContainsString('id="booking-phone-error"', $res->body());
        $this->assertStringContainsString('id="smeny"', $res->body());
    }

    public function test_misconfigured_guard_shows_fallback(): void
    {
        $app = TestApp::make(['env' => 'prod', 'appSecret' => '', 'fakeNow' => null]);
        $body = TestApp::get($app, '/booking/')->body();
        $this->assertStringNotContainsString('<form', $body);
        $this->assertStringContainsString('Форма сейчас недоступна', $body);
    }

    /** @return list<string> */
    private function optionValues(string $html): array
    {
        preg_match('#<select.*?</select>#s', $html, $s);
        preg_match_all('#<option value="([^"]*)"#', $s[0] ?? '', $m);

        return array_values(array_filter($m[1], static fn (string $v): bool => $v !== ''));
    }
}

