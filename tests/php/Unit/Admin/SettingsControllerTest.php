<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\App;
use Groza\Http\Response;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class SettingsControllerTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        Session::reset();
        $this->app = TestApp::make();
        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
    }

    /** @return array<string,mixed> */
    private function stored(): array
    {
        return $this->app->store()->read('settings');
    }

    /** @param array<string,mixed> $over @param array<string,mixed> $extra */
    private function save(array $over, array $extra = []): Response
    {
        $form = $over + $this->stored();

        return AdminHttp::postWithToken($this->app, '/admin/settings/', ['_hash' => $this->app->store()->hash('settings'), 'settings' => $form] + $extra);
    }

    public function test_page_has_all_fields_with_anchors(): void
    {
        $res = AdminHttp::get($this->app, '/admin/settings/');
        $this->assertSame(200, $res->status());
        foreach (['phone', 'whatsapp', 'email', 'mailTo', 'hoursWeekdays', 'hoursWeekend', 'officeAddress', 'postalAddress', 'vk', 'youtube', 'vkReviewsUrl', 'metrikaId'] as $key) {
            $this->assertStringContainsString('id="' . $key . '"', $res->body(), $key);
            $this->assertStringContainsString('name="settings[' . $key . ']"', $res->body(), $key);
        }
        $this->assertStringContainsString('data-draft', $res->body());
    }

    public function test_phone_normalized_and_mailto_validated(): void
    {
        $res = $this->save(['phone' => '8 (950) 441-12-99', 'whatsapp' => '+7(950)4411299', 'mailTo' => 'not-an-email']);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('адрес электронной почты', $res->body());
        $this->assertStringContainsString('not-an-email', $res->body(), 'введённое сохранено в форме');
        $this->assertNotSame('not-an-email', $this->stored()['mailTo'] ?? null);

        $ok = $this->save(['phone' => '8 (950) 441-12-99', 'whatsapp' => '9504411299', 'mailTo' => 'office@example.ru']);
        $this->assertSame(303, $ok->status());
        $this->assertSame('/admin/settings/?saved=1', $ok->header('Location'));
        $this->assertSame('+79504411299', $this->stored()['phone']);
        $this->assertSame('+79504411299', $this->stored()['whatsapp']);
        $this->assertSame('office@example.ru', $this->stored()['mailTo']);
        $last = $this->app->audit()->recent(1)[0];
        $this->assertSame('settings.update', $last['action']);
    }

    public function test_bad_phone_rejected(): void
    {
        $res = $this->save(['phone' => '12345']);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('номер', $res->body());
    }

    public function test_email_must_be_single_valid_address(): void
    {
        foreach (['a@b.ru, c@d.ru', "a@b.ru\nBcc: x@y.ru", 'a@b.ru;c@d.ru', 'Имя <a@b.ru>', 'a b@c.ru'] as $bad) {
            $this->assertSame(422, $this->save(['mailTo' => $bad])->status(), $bad);
            $this->assertSame(422, $this->save(['email' => $bad])->status(), $bad);
        }
        $this->assertSame(303, $this->save(['mailTo' => 'ok@example.ru', 'email' => 'info@example.ru'])->status());
    }

    public function test_url_fields_accept_only_https(): void
    {
        foreach (['vk', 'youtube', 'vkReviewsUrl'] as $key) {
            foreach (['javascript:alert(1)', 'http://vk.com/x', 'ftp://a.ru', '//evil.ru', 'vk.com/grozacamp', 'https://', 'https://a.ru/x y', 'https://a.ru/"onmouseover="x', 'data:text/html,1'] as $bad) {
                $res = $this->save([$key => $bad]);
                $this->assertSame(422, $res->status(), "$key $bad");
                $this->assertStringContainsString('https://', $res->body());
            }
        }
        $this->assertSame(303, $this->save(['vk' => 'https://vk.com/grozacamp', 'youtube' => 'https://www.youtube.com/@groza', 'vkReviewsUrl' => ''])->status());
        $this->assertSame('', $this->stored()['vkReviewsUrl']);
    }

    public function test_metrika_id_digits_only(): void
    {
        foreach (['abc', '123 456', '12345678901234', '1;alert(1)', '12.5'] as $bad) {
            $this->assertSame(422, $this->save(['metrikaId' => $bad])->status(), $bad);
        }
        $this->assertSame(303, $this->save(['metrikaId' => '12345678'])->status());
        $this->assertSame('12345678', $this->stored()['metrikaId']);
        $this->assertSame(303, $this->save(['metrikaId' => ''])->status());
    }

    public function test_unknown_setting_key_rejected(): void
    {
        $before = $this->app->store()->hash('settings');
        $res = $this->save(['evil' => 'x']);
        $this->assertSame(400, $res->status());
        $this->assertSame($before, $this->app->store()->hash('settings'));
    }

    public function test_clearing_mandatory_phone_needs_confirm(): void
    {
        $res = $this->save(['phone' => '']);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Это обязательный пункт для проверки (п. 7.а приказа № 201). Сохранить пустым?', $res->body());
        $this->assertNotSame('', $this->stored()['phone']);

        $ok = $this->save(['phone' => ''], ['confirm_empty' => ['phone' => '1']]);
        $this->assertSame(303, $ok->status());
        $this->assertSame('', $this->stored()['phone']);
    }

    public function test_text_fields_are_single_line_and_trimmed(): void
    {
        $this->save(['officeAddress' => "  г. Пермь,\nул. Ленина  ", 'hoursWeekdays' => "Пн–Пт\t10–19"]);
        $this->assertSame('г. Пермь, ул. Ленина', $this->stored()['officeAddress']);
        $this->assertSame('Пн–Пт 10–19', $this->stored()['hoursWeekdays']);
    }

    public function test_conflict_409(): void
    {
        $stale = $this->app->store()->hash('settings');
        $s = $this->stored();
        $s['hoursWeekend'] = 'кто-то изменил';
        $this->app->store()->write('settings', $s, 'other');

        $res = AdminHttp::postWithToken($this->app, '/admin/settings/', ['_hash' => $stale, 'settings' => ['officeAddress' => 'Мой адрес']]);
        $this->assertSame(409, $res->status());
        $this->assertStringContainsString('Мой адрес', $res->body());
        $this->assertSame('кто-то изменил', $this->stored()['hoursWeekend']);
    }

    public function test_change_is_reflected_on_public_site_and_hrefs_are_safe(): void
    {
        $this->save(['officeAddress' => 'г. Пермь, ул. Новая, 1', 'phone' => '+7 (342) 111-22-33']);
        $home = TestApp::get($this->app, '/contacts/')->body();
        $this->assertStringContainsString('ул. Новая, 1', $home);
        $this->assertStringContainsString('tel:+73421112233', $home);
    }

    public function test_stored_unsafe_urls_never_reach_hrefs(): void
    {
        $s = $this->stored();
        $s['vk'] = 'javascript:alert(1)';
        $s['youtube'] = 'data:text/html,<script>1</script>';
        $s['vkReviewsUrl'] = 'javascript:alert(2)';
        $this->app->store()->write('settings', $s, 'test');
        foreach (['/', '/contacts/', '/info/', '/booking/'] as $path) {
            $body = TestApp::get($this->app, $path)->body();
            $this->assertStringNotContainsString('javascript:', $body, $path);
            $this->assertStringNotContainsString('data:text/html', $body, $path);
        }
    }

    public function test_csrf_required(): void
    {
        $hash = $this->app->store()->hash('settings');
        $this->assertSame(419, AdminHttp::post($this->app, '/admin/settings/', ['_hash' => $hash, 'settings' => ['phone' => '+79990000000']])->status());
        $this->assertSame($hash, $this->app->store()->hash('settings'));
    }
}
