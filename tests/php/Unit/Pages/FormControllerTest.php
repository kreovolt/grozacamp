<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Forms\FormGuard;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Mail\ArrayTransport;
use Groza\Mail\MailException;
use Groza\Mail\MailTransport;
use Groza\Mail\Message;
use Groza\Support\FixedClock;
use Groza\Support\RateLimiter;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Приложение в тестах живёт в 2027-01-15 12:00 (Пермь); форма «показана» за 30 с до того. */
final class FormControllerTest extends TestCase
{
    private function app(): App
    {
        return TestApp::make();
    }

    /** @return array<string,string> */
    private function tokens(App $app, string $formId, int $secondsAgo = 30): array
    {
        $shown = $app->clock()->now()->modify("-{$secondsAgo} seconds")->format(DATE_ATOM);
        $clock = new FixedClock($shown);

        return (new FormGuard($app->config()['appSecret'], new RateLimiter($app->config()['storageDir'] . '/ratelimit', $clock), $clock, $app->config()['baseUrl']))
            ->fields($formId);
    }

    /** @param array<string,mixed> $over */
    private function booking(App $app, array $over = [], array $headers = [], int $secondsAgo = 30): Response
    {
        $post = $over + $this->tokens($app, 'booking', $secondsAgo) + [
            'shift' => 'spasatel-2027', 'parentName' => 'Иванова Анна', 'phone' => '8 950 441-12-99',
            'childAge' => '11', 'comment' => '', 'consent' => 'yes',
        ];

        return TestApp::post($app, '/form/booking/', $post, $headers);
    }

    private function sent(App $app): array
    {
        $t = $app->mailTransport();
        $this->assertInstanceOf(ArrayTransport::class, $t);

        return $t->sent;
    }

    public function test_success_without_js_redirects(): void
    {
        $app = $this->app();
        $res = $this->booking($app);
        $this->assertSame(303, $res->status());
        $this->assertSame('/spasibo/?f=booking', $res->header('Location'));
        $this->assertCount(1, $this->sent($app));
        $m = $this->sent($app)[0];
        $this->assertSame('Заявка на путёвку: Спасатель — Иванова Анна', $m->subject);
        $this->assertSame('2474347@bk.ru', $m->to);
        $this->assertStringContainsString('+7 (950) 441-12-99', $m->text);
    }

    public function test_success_with_js_returns_json(): void
    {
        $app = $this->app();
        $res = $this->booking($app, [], ['Accept' => 'application/json']);
        $this->assertSame(200, $res->status());
        $this->assertSame('{"ok":true}', $res->body());
        $this->assertStringContainsString('application/json', (string) $res->header('Content-Type'));
    }

    public function test_any_shift_sends_mail_without_shift_title(): void
    {
        $app = $this->app();
        $this->booking($app, ['shift' => 'any']);
        $this->assertStringContainsString('смена не выбрана', $this->sent($app)[0]->subject);
    }

    public function test_closed_and_hidden_shifts_are_not_accepted(): void
    {
        $app = $this->app();
        foreach (['pogranichnik-2027', 'vperyod-i-vverh-2027'] as $slug) {
            $res = $this->booking($app, ['shift' => $slug]);
            $this->assertSame(422, $res->status(), $slug);
        }
        $this->assertSame([], $this->sent($app));
    }

    public function test_honeypot_pretends_success(): void
    {
        $app = $this->app();
        $res = $this->booking($app, ['website' => 'http://spam.example']);
        $this->assertSame(303, $res->status());
        $this->assertStringStartsWith('/spasibo/', (string) $res->header('Location'));
        $this->assertSame([], $this->sent($app));
        $json = $this->booking($app, ['website' => 'x'], ['Accept' => 'application/json']);
        $this->assertSame('{"ok":true}', $json->body());
        $this->assertSame([], $this->sent($app));
    }

    public function test_validation_error_rerenders_with_values(): void
    {
        $app = $this->app();
        $res = $this->booking($app, ['parentName' => 'Мария <b>Т</b>', 'phone' => '123', 'consent' => '']);
        $this->assertSame(422, $res->status());
        $html = $res->body();
        $this->assertStringContainsString('Мария &lt;b&gt;Т&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Т</b>', $html);
        $this->assertStringContainsString('Нужно согласие на обработку данных', $html);
        $this->assertMatchesRegularExpression('/name="_ts" value="\d+"/', $html);
        $this->assertSame([], $this->sent($app));
    }

    public function test_validation_error_json(): void
    {
        $app = $this->app();
        $res = $this->booking($app, ['phone' => '123'], ['Accept' => 'application/json']);
        $this->assertSame(422, $res->status());
        $data = json_decode($res->body(), true);
        $this->assertFalse($data['ok']);
        $this->assertArrayHasKey('phone', $data['errors']);
        $this->assertArrayHasKey('_ts', $data['guard']);
        $this->assertArrayHasKey('_sig', $data['guard']);
    }

    public function test_too_fast_asks_to_retry_and_keeps_values(): void
    {
        $app = $this->app();
        $res = $this->booking($app, ['parentName' => 'Ольга'], [], 1);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Ольга', $res->body());
        $this->assertSame([], $this->sent($app));
    }

    public function test_foreign_origin_is_refused_with_fallback(): void
    {
        $app = $this->app();
        $res = $this->booking($app, [], ['Origin' => 'https://evil.example']);
        $this->assertSame(403, $res->status());
        $this->assertSame([], $this->sent($app));
    }

    public function test_mail_failure_shows_phone(): void
    {
        $app = $this->app();
        $app->setMailTransport(new class () implements MailTransport {
            public function send(Message $m): void
            {
                throw new MailException('smtp down');
            }
        });
        $res = $this->booking($app);
        $this->assertSame(503, $res->status());
        $this->assertStringContainsString('+7 (342) 247-43-47', $res->body());
        $this->assertStringContainsString('wa.me/79991239300', $res->body());

        $json = $this->booking($app, [], ['Accept' => 'application/json']);
        $this->assertSame(503, $json->status());
        $this->assertSame('{"ok":false,"fallback":true}', $json->body());
    }

    public function test_rate_limited_page(): void
    {
        $app = $this->app();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(303, $this->booking($app)->status());
        }
        $res = $this->booking($app);
        $this->assertSame(429, $res->status());
        $this->assertStringContainsString('+7 (342) 247-43-47', $res->body());
        $json = $this->booking($app, [], ['Accept' => 'application/json']);
        $this->assertSame(429, $json->status());
        $this->assertSame('{"ok":false,"fallback":true}', $json->body());
    }

    public function test_log_has_no_personal_data(): void
    {
        $app = $this->app();
        $app->setMailTransport(new class () implements MailTransport {
            public function send(Message $m): void
            {
                throw new MailException('smtp down for Секретная Персона 9504411299');
            }
        });
        $this->booking($app, ['parentName' => 'Секретная Персона', 'phone' => '9504411299']);        // сбой почты
        $this->booking($app, ['parentName' => 'Секретная Персона', 'phone' => '1']);                  // ошибка проверки
        $this->booking($app, ['parentName' => 'Секретная Персона'], [], 1);                           // слишком быстро
        $this->booking($app, ['website' => 'Секретная Персона']);                                      // honeypot

        $logs = glob($app->config()['storageDir'] . '/logs/*.log') ?: [];
        $this->assertNotEmpty($logs);
        $all = '';
        foreach ($logs as $f) {
            $all .= file_get_contents($f);
        }
        $this->assertStringContainsString('booking', $all);
        $this->assertStringContainsString('mail_failed', $all);
        foreach (['Секретная', '9504411299', '950', '127.0.0.1', '+7'] as $needle) {
            $this->assertStringNotContainsString($needle, $all, $needle);
        }
    }

    public function test_mail_failure_log_has_reason_code_only(): void
    {
        $app = $this->app();
        $app->setMailTransport(new class () implements MailTransport {
            public int $n = 0;

            public function send(Message $m): void
            {
                throw ++$this->n === 1
                    ? new MailException('ответ сервера с телефоном 9504411299', 'smtp_auth')
                    : new \RuntimeException('что-то про Секретная Персона');
            }
        });
        $this->booking($app, ['parentName' => 'Секретная Персона']);
        $this->booking($app, ['parentName' => 'Секретная Персона']);
        $log = (string) file_get_contents($app->config()['storageDir'] . '/logs/forms.log');
        $this->assertStringContainsString(' booking mail_failed:smtp_auth', $log);
        $this->assertStringContainsString(' booking mail_failed:error', $log);
        $this->assertStringNotContainsString('9504411299', $log);
        $this->assertStringNotContainsString('Секретная', $log);
    }

    public function test_vacancy_flow(): void
    {
        $app = $this->app();
        $post = $this->tokens($app, 'vacancy') + [
            'fullName' => 'Петров Пётр', 'phone' => '9504411299', 'email' => 'p@example.ru', 'age' => '22',
            'position' => 'Вожатый (пример)', 'experience' => 'Два сезона', 'shifts' => '', 'vk' => '', 'consent' => 'yes',
        ];
        $res = TestApp::post($app, '/form/vacancy/', $post);
        $this->assertSame(303, $res->status());
        $this->assertSame('/spasibo/?f=vacancy', $res->header('Location'));
        $m = $this->sent($app)[0];
        $this->assertSame('Анкета на вакансию: Вожатый (пример) — Петров Пётр', $m->subject);
        $this->assertSame('p@example.ru', $m->replyTo);
    }

    public function test_vacancy_validation_error(): void
    {
        $app = $this->app();
        $res = TestApp::post($app, '/form/vacancy/', $this->tokens($app, 'vacancy') + ['fullName' => 'Пётр']);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Пётр', $res->body());
    }


    public function test_token_survives_validation_error_so_quick_resubmit_works(): void
    {
        $app = $this->app();
        $tokens = $this->tokens($app, 'booking', 3);
        $res = $this->booking($app, ['consent' => ''] + $tokens, ['Accept' => 'application/json'], 3);
        $this->assertSame(422, $res->status());
        $data = json_decode($res->body(), true);
        $this->assertSame($tokens, $data['guard']);

        $html = $this->booking($app, ['consent' => ''] + $tokens, [], 3);
        $this->assertStringContainsString('value="' . $tokens['_ts'] . '"', $html->body());
        $this->assertStringContainsString('value="' . $tokens['_sig'] . '"', $html->body());

        // сразу после ошибки: тот же токен, согласие отмечено — принято, а не «слишком быстро»
        $this->assertSame(303, $this->booking($app, $data['guard'], [], 3)->status());
    }

    public function test_too_fast_keeps_token_and_passes_three_seconds_later(): void
    {
        $early = TestApp::make();
        $tokens = $this->tokens($early, 'booking', 1);
        $res = $this->booking($early, $tokens, ['Accept' => 'application/json'], 1);
        $this->assertSame(422, $res->status());
        $guard = json_decode($res->body(), true)['guard'];
        $this->assertSame($tokens, $guard);

        $later = TestApp::make(['fakeNow' => '2027-01-15T12:00:02+05:00']);
        $this->assertSame(303, $this->booking($later, $guard, [], 1)->status());
    }

    public function test_expired_token_is_replaced_with_fresh_one(): void
    {
        $app = $this->app();
        $tokens = $this->tokens($app, 'booking', 3 * 3600);
        $res = $this->booking($app, $tokens, ['Accept' => 'application/json'], 3 * 3600);
        $this->assertSame(422, $res->status());
        $this->assertNotSame($tokens['_ts'], json_decode($res->body(), true)['guard']['_ts']);
    }

    public function test_token_close_to_expiry_is_replaced_but_a_fresh_one_is_kept(): void
    {
        $app = $this->app();
        $old = $this->tokens($app, 'booking', FormGuard::MAX_SECONDS - 300); // ещё действует, но живёт меньше 10 минут
        $res = $this->booking($app, ['consent' => ''] + $old, ['Accept' => 'application/json'], FormGuard::MAX_SECONDS - 300);
        $this->assertSame(422, $res->status());
        $this->assertNotSame($old['_ts'], json_decode($res->body(), true)['guard']['_ts']);

        $fresh = $this->tokens($app, 'booking', FormGuard::MAX_SECONDS - 700);
        $res = $this->booking($app, ['consent' => ''] + $fresh, ['Accept' => 'application/json'], FormGuard::MAX_SECONDS - 700);
        $this->assertSame($fresh, json_decode($res->body(), true)['guard']);
    }

    public function test_booking_page_and_error_rerender_show_fallback_when_guard_is_not_configured(): void
    {
        $app = TestApp::make(['env' => 'prod', 'appSecret' => '', 'fakeNow' => null]);
        $this->assertNull($app->guardFields('booking'));
        $page = TestApp::get($app, '/booking/');
        $this->assertSame(200, $page->status());
        $this->assertStringNotContainsString('name="_ts"', $page->body());
        $this->assertStringContainsString('+7 (342) 247-43-47', $page->body());

        $vacancies = TestApp::get($app, '/vacancies/');
        $this->assertSame(200, $vacancies->status());
        $this->assertStringNotContainsString('name="_ts"', $vacancies->body());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badSecrets')]
    public function test_misconfigured_secret_in_prod_gives_fallback(string $secret): void
    {
        $app = TestApp::make(['env' => 'prod', 'appSecret' => $secret, 'fakeNow' => null]);
        $app->setMailTransport(new ArrayTransport());
        $post = ['_ts' => '1', '_sig' => 'x', 'parentName' => 'Анна'];
        $res = TestApp::post($app, '/form/booking/', $post);
        $this->assertSame(503, $res->status());
        $this->assertStringContainsString('+7 (342) 247-43-47', $res->body());
        $this->assertStringContainsString('wa.me/79991239300', $res->body());
        $json = TestApp::post($app, '/form/booking/', $post, ['Accept' => 'application/json']);
        $this->assertSame('{"ok":false,"fallback":true}', $json->body());
        $this->assertStringContainsString('misconfigured', (string) file_get_contents($app->config()['storageDir'] . '/logs/forms.log'));
    }

    public static function badSecrets(): array
    {
        return [
            'empty' => [''],
            'placeholder' => ['замените-на-длинную-случайную-строку'],
            'short' => ['abcdefghij0123456789abcdefghij0'],
        ];
    }

    public function test_misconfigured_secret_does_not_break_form_page_render(): void
    {
        $app = TestApp::make(['env' => 'dev', 'appSecret' => '']);
        $this->assertSame(200, TestApp::get($app, '/booking/')->status()); // dev: запасной секрет

        $prod = TestApp::make(['env' => 'prod', 'appSecret' => '', 'fakeNow' => null]);
        $html = $prod->view()->render('forms/booking', ['values' => [], 'errors' => [], 'options' => ['shifts' => []], 'guard' => null]);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('+7 (342) 247-43-47', $html);
    }

    public function test_valid_long_secret_in_prod_is_accepted(): void
    {
        $app = TestApp::make(['env' => 'prod', 'baseUrl' => 'https://grozacamp.ru', 'appSecret' => str_repeat('k', 40), 'fakeNow' => null]);
        $this->assertSame(['_ts', '_sig'], array_keys($app->guard()->fields('booking')));
    }

    public function test_mail_to_defaults_when_setting_missing_or_invalid(): void
    {
        foreach (['', 'not-an-email', null] as $value) {
            $app = $this->app();
            $settings = $app->store()->read('settings');
            if ($value === null) {
                unset($settings['mailTo']);
            } else {
                $settings['mailTo'] = $value;
            }
            $app->store()->write('settings', $settings, 'test');
            $this->booking($app);
            $this->assertSame('2474347@bk.ru', $this->sent($app)[0]->to, var_export($value, true));
        }
        $app = $this->app();
        $app->store()->write('settings', ['mailTo' => 'office@example.ru'] + $app->store()->read('settings'), 'test');
        $this->booking($app);
        $this->assertSame('office@example.ru', $this->sent($app)[0]->to);
    }

    public function test_thanks_page(): void
    {
        $app = $this->app();
        $res = TestApp::get($app, '/spasibo/', ['f' => 'booking']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Спасибо! Мы перезвоним', $res->body());
        $this->assertStringContainsString('noindex', $res->body());
        $this->assertSame(200, TestApp::get($app, '/spasibo/')->status());
    }

    public function test_thanks_hours_come_from_settings(): void
    {
        $app = $this->app();
        $app->store()->write('settings', ['hoursWeekdays' => 'Пн–Сб 9:00–20:00', 'hoursWeekend' => ''] + $app->store()->read('settings'), 'test');
        $body = TestApp::get($app, '/spasibo/', ['f' => 'booking'])->body();
        $this->assertStringContainsString('Позвоним в рабочее время — Пн–Сб 9:00–20:00.', $body);
        $this->assertStringNotContainsString('10:00', $body);

        $app->store()->write('settings', ['hoursWeekdays' => '', 'hoursWeekend' => ''] + $app->store()->read('settings'), 'test');
        $this->assertStringContainsString('Позвоним в рабочее время.', TestApp::get($app, '/spasibo/', ['f' => 'booking'])->body());
    }

    public function test_get_on_form_endpoint_is_405(): void
    {
        $this->assertSame(405, TestApp::get($this->app(), '/form/booking/')->status());
    }
}
