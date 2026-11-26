<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\Auth;
use Groza\Admin\Users;
use Groza\App;
use Groza\Data\JsonStore;
use Groza\Http\Request;
use Groza\Support\Csrf;
use Groza\Support\RateLimiter;
use Groza\Support\FixedClock;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        Session::reset();
        $this->app = TestApp::make();
        AdminHttp::user($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
    }

    private function clock(): FixedClock
    {
        $clock = $this->app->clock();
        $this->assertInstanceOf(FixedClock::class, $clock);

        return $clock;
    }

    private function auth(): Auth
    {
        return $this->app->auth();
    }

    private function startSession(): void
    {
        Session::start($this->app->config());
    }

    // ---------- вход ----------

    public function test_login_ok_sets_user_and_regenerates_id(): void
    {
        $this->startSession();
        $before = Session::id();
        $csrfBefore = Csrf::token();

        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.0.0.1'));

        $this->assertSame(['login' => 'olga', 'name' => 'Ольга'], $this->auth()->user());
        $this->assertNotSame($before, Session::id(), 'id сессии после входа должен смениться (fixation)');
        $this->assertFalse(Csrf::verify($csrfBefore), 'токен CSRF после входа обновляется');
    }

    public function test_login_is_case_and_whitespace_insensitive(): void
    {
        $this->startSession();
        $this->assertSame('ok', $this->auth()->attempt("  OLGA\t", AdminHttp::PASSWORD, '10.0.0.1'));
    }

    public function test_wrong_password_generic_message(): void
    {
        $unknown = AdminHttp::login($this->app, 'nobody', 'whatever-long-pass');
        $wrong = AdminHttp::login($this->app, 'olga', 'wrong-password-1');

        foreach ([$unknown, $wrong] as $res) {
            $this->assertSame(200, $res->status());
            $this->assertStringContainsString('Неверный логин или пароль', $res->body());
        }
        $this->assertSame($this->stripToken($unknown->body(), 'nobody'), $this->stripToken($wrong->body(), 'olga'));
        $this->assertNull($this->auth()->user());
    }

    private function stripToken(string $html, string $login): string
    {
        return str_replace([Csrf::token(), $login], '', $html);
    }

    public function test_failed_login_page_never_echoes_password(): void
    {
        $res = AdminHttp::login($this->app, 'olga', 'secret-<b>-pass');

        $this->assertStringNotContainsString('secret-', $res->body());
        $this->assertStringContainsString('value="olga"', $res->body());
    }

    public function test_unknown_login_uses_precomputed_dummy_hash_of_the_same_algorithm(): void
    {
        $this->startSession();
        $this->assertSame('invalid', $this->auth()->attempt('nobody', 'whatever-long-pass', '10.0.0.1'));

        $dummy = $this->app->store()->read('auth-meta')['dummy'] ?? '';
        $real = (new Users($this->app->store()))->find('olga')['hash'];
        $this->assertNotSame('', $dummy);
        $this->assertSame(password_get_info($real)['algo'], password_get_info($dummy)['algo']);
        $this->assertSame(password_get_info($real)['options'], password_get_info($dummy)['options']);

        $again = $this->app->store()->hash('auth-meta');
        $this->auth()->attempt('nobody2', 'whatever-long-pass', '10.0.0.1');
        $this->assertSame($again, $this->app->store()->hash('auth-meta'), 'фиктивный хеш считается один раз');
    }

    public function test_old_hash_is_rehashed_on_successful_login(): void
    {
        $old = password_hash(AdminHttp::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $data = $this->app->store()->read('users');
        $data['items'][0]['hash'] = $old;
        $this->app->store()->write('users', $data, 'test');

        $this->startSession();
        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.0.0.1'));

        $new = (new Users($this->app->store()))->find('olga')['hash'];
        $this->assertNotSame($old, $new);
        $this->assertFalse(password_needs_rehash($new, PASSWORD_DEFAULT));
        $this->assertTrue(password_verify(AdminHttp::PASSWORD, $new));
        $this->assertNotNull($this->auth()->user(), 'сессия переживает перехеширование');
    }

    // ---------- блокировка ----------

    public function test_lockout_after_5_failures(): void
    {
        $this->startSession();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('invalid', $this->auth()->attempt('olga', 'wrong-password-' . $i, '10.0.0.' . $i));
        }

        $this->assertSame('locked', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.9.9.9'), 'верный пароль при блокировке не пускает');
        $this->assertNull($this->auth()->user());

        $this->clock()->advance('+14 minutes');
        $this->assertSame('locked', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.9.9.9'));

        $this->clock()->advance('+2 minutes');
        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.9.9.9'));
    }

    public function test_lockout_cannot_be_dodged_by_case_or_whitespace(): void
    {
        $this->startSession();
        foreach (['olga', 'OLGA', ' olga', 'olga ', "Olga\u{200B}"] as $i => $variant) {
            $this->assertSame('invalid', $this->auth()->attempt($variant, 'wrong-password-1', '10.0.0.' . $i));
        }

        $this->assertSame('locked', $this->auth()->attempt('oLgA', AdminHttp::PASSWORD, '10.8.8.8'));
    }

    public function test_ip_threshold_is_20_failures_across_different_logins(): void
    {
        $this->startSession();
        for ($i = 0; $i < 20; $i++) { // за одним NAT — весь офис: 5 сбоев с адреса ещё не блокируют
            $this->assertSame('invalid', $this->auth()->attempt('user' . $i, 'wrong-password-1', '192.0.2.7'));
        }

        $this->assertSame('locked', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '192.0.2.7'));
        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '192.0.2.8'), 'другой адрес не затронут');
    }

    public function test_ip_below_threshold_still_logs_in_and_user_threshold_stays_5(): void
    {
        $this->startSession();
        for ($i = 0; $i < 19; $i++) {
            $this->auth()->attempt('user' . $i, 'wrong-password-1', '192.0.2.9');
        }
        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '192.0.2.9'));
        $this->auth()->logout();

        // а по логину порог прежний — 5, хоть адрес и «чистый»
        $this->startSession();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('invalid', $this->auth()->attempt('petr', 'wrong-password-1', '192.0.2.50'));
        }
        $this->assertSame('locked', $this->auth()->attempt('petr', 'wrong-password-1', '192.0.2.50'));
    }

    public function test_lockout_of_unknown_login_looks_the_same(): void
    {
        $this->startSession();
        for ($i = 0; $i < 5; $i++) {
            $this->auth()->attempt('ghost', 'wrong-password-1', '10.0.0.' . $i);
        }

        $this->assertSame('locked', $this->auth()->attempt('ghost', 'wrong-password-1', '10.5.5.5'));
    }

    public function test_successful_login_resets_that_logins_counter(): void
    {
        $this->startSession();
        for ($i = 0; $i < 4; $i++) {
            $this->auth()->attempt('olga', 'wrong-password-1', '10.0.0.' . $i);
        }
        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.1.1.1'));
        $this->auth()->logout();

        $this->startSession();
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame('invalid', $this->auth()->attempt('olga', 'wrong-password-1', '10.2.2.' . $i));
        }
        $this->assertSame('ok', $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.3.3.3'));
    }

    public function test_locked_page_message_and_status(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AdminHttp::login($this->app, 'olga', 'wrong-password-1', '10.0.0.' . $i);
        }
        $res = AdminHttp::login($this->app, 'olga', AdminHttp::PASSWORD, '10.7.7.7');

        $this->assertSame(429, $res->status());
        $this->assertStringContainsString('Слишком много попыток. Попробуйте через 15 минут.', $res->body());
        $this->assertNull($this->auth()->user());
    }

    // ---------- время жизни сессии ----------

    public function test_idle_timeout(): void
    {
        $this->startSession();
        $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.0.0.1');
        $this->clock()->advance('+29 minutes');
        $this->assertNotNull($this->auth()->user());

        $this->clock()->advance('+31 minutes');
        $this->assertNull($this->auth()->user());
        $this->assertNull($this->auth()->user(), 'и не воскресает');
    }

    public function test_activity_extends_idle_but_not_absolute_lifetime(): void
    {
        $this->startSession();
        $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.0.0.1');
        for ($i = 0; $i < 35; $i++) { // 35 × 20 мин = 11 ч 40 мин
            $this->clock()->advance('+20 minutes');
            $this->assertNotNull($this->auth()->user(), "шаг $i");
        }

        $this->clock()->advance('+20 minutes'); // 12 ч 00 мин — граница
        $this->clock()->advance('+1 second');
        $this->assertNull($this->auth()->user());
    }

    public function test_session_dies_when_user_removed_or_password_changed(): void
    {
        $this->startSession();
        $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.0.0.1');
        $this->assertNotNull($this->auth()->user());

        (new Users($this->app->store()))->setPassword('olga', 'brand-new-password');
        $this->assertNull($this->auth()->user(), 'смена пароля в другом месте закрывает прежние сессии');

        $this->startSession();
        $this->auth()->attempt('olga', 'brand-new-password', '10.0.0.1');
        $this->assertNotNull($this->auth()->user());
        (new Users($this->app->store()))->remove('olga');
        $this->assertNull($this->auth()->user());
    }

    public function test_logout_destroys_session(): void
    {
        $this->startSession();
        $this->auth()->attempt('olga', AdminHttp::PASSWORD, '10.0.0.1');
        $id = Session::id();
        $this->auth()->logout();

        $this->assertNull($this->auth()->user());
        $this->assertNotSame($id, Session::id());
    }

    // ---------- доступ и переходы ----------

    public function test_admin_requires_login(): void
    {
        $res = AdminHttp::get($this->app, '/admin/');

        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/login/?next=%2Fadmin%2F', $res->header('Location'));
    }

    public function test_unknown_admin_paths_do_not_reveal_routes_to_anonymous(): void
    {
        $res = AdminHttp::get($this->app, '/admin/shifts/lonely/');

        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/login/?next=%2Fadmin%2Fshifts%2Flonely%2F', $res->header('Location'));
    }

    public function test_unauthenticated_post_with_valid_token_redirects_to_login(): void
    {
        AdminHttp::get($this->app, '/admin/login/');
        $res = AdminHttp::postWithToken($this->app, '/admin/password/', ['current' => 'x']);

        $this->assertSame(302, $res->status());
        $this->assertStringStartsWith('/admin/login/', (string) $res->header('Location'));
    }

    /** @dataProvider evilNext */
    public function test_next_cannot_leave_admin(string $next): void
    {
        $res = AdminHttp::login($this->app, 'olga', AdminHttp::PASSWORD, '127.0.0.1', $next);

        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/', $res->header('Location'), $next);
    }

    /** @return array<string,array{string}> */
    public static function evilNext(): array
    {
        return [
            'absolute' => ['https://evil.example'], 'scheme-relative' => ['//evil.example'], 'backslash' => ['/\\evil.example'],
            'admin-then-backslash' => ['/admin/\\evil.example'], 'crlf' => ["/admin/\r\nX-Evil: 1"], 'admin-prefix-only' => ['/administrator/'],
            'javascript' => ['javascript:alert(1)'], 'dotdot' => ['/admin/../evil'], 'root' => ['/'], 'empty' => [''],
            'space' => ['/admin/ x'], 'tab' => ["/admin/\tx"], 'login-loop' => ['/admin/login/'], 'colon-in-first-segment' => ['/admin:@evil.example'],
        ];
    }

    public function test_safe_next_is_honoured_after_login(): void
    {
        $res = AdminHttp::login($this->app, 'olga', AdminHttp::PASSWORD, '127.0.0.1', '/admin/password/?a=1');

        $this->assertSame('/admin/password/?a=1', $res->header('Location'));
    }

    public function test_already_logged_in_login_page_redirects(): void
    {
        AdminHttp::login($this->app);
        $res = AdminHttp::get($this->app, '/admin/login/');

        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/', $res->header('Location'));
    }

    // ---------- CSRF ----------

    public function test_post_without_csrf_rejected(): void
    {
        $before = $this->app->store()->hash('shifts');
        AdminHttp::login($this->app);

        foreach ([[], ['_csrf' => ''], ['_csrf' => 'forged'], ['_csrf' => ['x']]] as $post) {
            $res = AdminHttp::post($this->app, '/admin/shifts/x/status/', $post);
            $this->assertSame(419, $res->status());
            $this->assertStringContainsString('Сессия устарела. Обновите страницу и повторите.', $res->body());
            $this->assertStringContainsString('href="/admin/', $res->body());
        }
        $this->assertSame($before, $this->app->store()->hash('shifts'));
    }

    public function test_csrf_checked_before_routing_even_for_unknown_paths_and_all_methods(): void
    {
        foreach (['/admin/nothing-here/', '/admin/logout/', '/admin/login/', '/admin/'] as $path) {
            $this->assertSame(419, AdminHttp::post($this->app, $path, [])->status(), $path);
        }
        foreach (['PUT', 'DELETE', 'PATCH'] as $method) {
            $this->assertSame(419, AdminHttp::post($this->app, '/admin/x/', [], '127.0.0.1', $method)->status(), $method);
        }
    }

    public function test_csrf_not_accepted_from_query_string(): void
    {
        AdminHttp::get($this->app, '/admin/login/');
        $res = $this->app->handle(\Groza\Http\Request::create('POST', '/admin/login/', ['_csrf' => Csrf::token()], ['login' => 'olga', 'password' => AdminHttp::PASSWORD]));

        $this->assertSame(419, $res->status());
    }

    public function test_login_post_requires_token_from_login_page(): void
    {
        $res = AdminHttp::post($this->app, '/admin/login/', ['login' => 'olga', 'password' => AdminHttp::PASSWORD]);

        $this->assertSame(419, $res->status());
        $this->assertNull($this->auth()->user());
    }

    public function test_logout_is_post_only_with_csrf(): void
    {
        AdminHttp::login($this->app);

        $this->assertSame(405, AdminHttp::get($this->app, '/admin/logout/')->status());
        $this->assertNotNull($this->auth()->user(), 'GET не выходит');
        $this->assertSame(419, AdminHttp::post($this->app, '/admin/logout/')->status());
        $this->assertNotNull($this->auth()->user());

        $res = AdminHttp::postWithToken($this->app, '/admin/logout/');
        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/login/', $res->header('Location'));
        $this->assertNull($this->auth()->user());
    }

    // ---------- ответы админки ----------

    public function test_every_admin_response_is_not_cached_and_noindex(): void
    {
        AdminHttp::login($this->app);
        $responses = [
            AdminHttp::get($this->app, '/admin/'),
            AdminHttp::get($this->app, '/admin/password/'),
            AdminHttp::get($this->app, '/admin/no-such-page/'),
            AdminHttp::post($this->app, '/admin/'),
            AdminHttp::postWithToken($this->app, '/admin/logout/'),
            AdminHttp::get($this->app, '/admin/login/'),
            AdminHttp::get($this->app, '/admin/'),
        ];
        foreach ($responses as $i => $res) {
            $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'), "ответ $i");
            $this->assertSame('noindex, nofollow', $res->header('X-Robots-Tag'), "ответ $i");
        }
        $this->assertSame(404, $responses[2]->status());
    }

    public function test_public_pages_are_sessionless_and_cacheable(): void
    {
        foreach (['/', '/booking/', '/contacts/'] as $path) {
            $res = AdminHttp::get($this->app, $path);
            $this->assertFalse(Session::isActive(), $path);
            $this->assertStringNotContainsString('no-store', (string) $res->header('Cache-Control'));
        }
    }

    public function test_dashboard_shows_name_and_logout_button_with_token(): void
    {
        AdminHttp::login($this->app);
        $res = AdminHttp::get($this->app, '/admin/');

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Админка', $res->body());
        $this->assertStringContainsString('Ольга', $res->body());
        $this->assertStringContainsString('action="/admin/logout/"', $res->body());
        $this->assertStringContainsString('name="_csrf" value="' . Csrf::token() . '"', $res->body());
        $this->assertStringContainsString('noindex', $res->body());
    }

    public function test_admin_templates_have_no_inline_script_or_style(): void
    {
        AdminHttp::login($this->app);
        $bodies = [AdminHttp::get($this->app, '/admin/')->body(), AdminHttp::get($this->app, '/admin/password/')->body()];
        AdminHttp::postWithToken($this->app, '/admin/logout/');
        $login = AdminHttp::get($this->app, '/admin/login/');
        $this->assertSame(200, $login->status());
        $this->assertStringContainsString('name="password"', $login->body());
        $bodies[] = $login->body();
        $bodies[] = AdminHttp::post($this->app, '/admin/x/')->body(); // 419
        foreach ($bodies as $body) {
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)/i', $body);
            $this->assertStringNotContainsString(' style=', $body);
            $this->assertStringNotContainsString('<style', $body);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $body);
        }
    }

    // ---------- пароль ----------

    public function test_password_change_flow(): void
    {
        AdminHttp::login($this->app);
        $id = Session::id();
        $form = [
            'current' => AdminHttp::PASSWORD, 'new' => 'brand-new-password', 'repeat' => 'brand-new-password',
        ];

        $res = AdminHttp::postWithToken($this->app, '/admin/password/', $form);

        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/password/?ok=1', $res->header('Location'));
        $this->assertNotSame($id, Session::id(), 'id сессии меняется при смене пароля');
        $this->assertNotNull($this->auth()->user(), 'сам пользователь остаётся в системе');
        $this->assertTrue(password_verify('brand-new-password', (new Users($this->app->store()))->find('olga')['hash']));
        $this->assertContains('auth.password.change', array_column($this->app->audit()->recent(), 'action'));
        $this->assertStringContainsString('Пароль изменён', AdminHttp::get($this->app, '/admin/password/', ['ok' => '1'])->body());
    }

    /** @dataProvider badPasswordForms */
    public function test_password_change_validation(array $form, string $message): void
    {
        AdminHttp::login($this->app);
        $res = AdminHttp::postWithToken($this->app, '/admin/password/', $form);

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($message, $res->body());
        $this->assertTrue(password_verify(AdminHttp::PASSWORD, (new Users($this->app->store()))->find('olga')['hash']));
        $this->assertStringNotContainsString($form['new'], $res->body());
    }

    /** @return array<string,array{array<string,string>,string}> */
    public static function badPasswordForms(): array
    {
        $p = AdminHttp::PASSWORD;

        return [
            'wrong current' => [['current' => 'not-my-password', 'new' => 'brand-new-password', 'repeat' => 'brand-new-password'], 'Текущий пароль указан неверно'],
            'too short' => [['current' => $p, 'new' => 'short-9ch', 'repeat' => 'short-9ch'], 'не короче 10 символов'],
            'mismatch' => [['current' => $p, 'new' => 'brand-new-password', 'repeat' => 'brand-new-passwor'], 'Пароли не совпадают'],
            'same' => [['current' => $p, 'new' => $p, 'repeat' => $p], 'отличаться от текущего'],
        ];
    }

    public function test_password_change_guess_attempts_are_limited(): void
    {
        AdminHttp::login($this->app);
        for ($i = 0; $i < 5; $i++) {
            AdminHttp::postWithToken($this->app, '/admin/password/', ['current' => 'guess-' . $i . '-xxxxx', 'new' => 'brand-new-password', 'repeat' => 'brand-new-password']);
        }
        $res = AdminHttp::postWithToken($this->app, '/admin/password/', ['current' => AdminHttp::PASSWORD, 'new' => 'brand-new-password', 'repeat' => 'brand-new-password']);

        $this->assertSame(429, $res->status());
        $this->assertTrue(password_verify(AdminHttp::PASSWORD, (new Users($this->app->store()))->find('olga')['hash']));
    }

    // ---------- журнал ----------

    public function test_login_events_audited_without_password(): void
    {
        AdminHttp::login($this->app, 'Mallory', 'my-secret-pass-123');
        AdminHttp::login($this->app, 'olga', AdminHttp::PASSWORD);
        AdminHttp::postWithToken($this->app, '/admin/logout/');

        $log = (string) file_get_contents($this->app->config()['storageDir'] . '/logs/audit.jsonl');
        $this->assertStringNotContainsString('my-secret-pass-123', $log);
        $this->assertStringNotContainsString(AdminHttp::PASSWORD, $log);
        $this->assertStringNotContainsString('Mallory', $log);
        $this->assertStringNotContainsString('mallory', $log);
        $this->assertStringNotContainsString('127.0.0.1', $log);
        $this->assertNotSame(substr(hash('sha256', 'mallory'), 0, 12), $this->app->audit()->recent()[2]['target'], 'не голый sha256');

        $rows = array_reverse($this->app->audit()->recent());
        $this->assertSame(['-', 'auth.login.fail', substr(hash_hmac('sha256', 'mallory', 'test-secret'), 0, 12)], [$rows[0]['actor'], $rows[0]['action'], $rows[0]['target']]);
        $this->assertSame('olga', $rows[1]['actor']);
        $this->assertSame('auth.login.ok', $rows[1]['action']);
        $this->assertSame('olga', $rows[2]['actor']);
        $this->assertSame('auth.logout', $rows[2]['action']);
    }

    public function test_actor_is_current_login_or_dash(): void
    {
        $this->assertSame('-', $this->app->actor());
        AdminHttp::login($this->app);
        $this->assertSame('olga', $this->app->actor());
    }

    public function test_lock_is_audited_once_at_the_moment_it_is_set_without_raw_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AdminHttp::login($this->app, 'Olga', 'wrong-password-1', '10.0.0.' . $i);
        }
        AdminHttp::login($this->app, 'Olga', 'wrong-password-1', '10.0.0.9'); // уже заблокирован
        AdminHttp::login($this->app, 'Olga', AdminHttp::PASSWORD, '10.0.0.9');

        $locked = array_values(array_filter($this->app->audit()->recent(), static fn (array $r): bool => $r['action'] === 'auth.login.locked'));
        $this->assertCount(1, $locked);
        $this->assertSame('-', $locked[0]['actor']);
        $this->assertSame(substr(hash_hmac('sha256', 'olga', 'test-secret'), 0, 12), $locked[0]['target']);
    }

    public function test_unknown_login_still_answers_invalid_when_store_is_not_writable(): void
    {
        $dir = sys_get_temp_dir() . '/groza-ro-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/data', 'a file, not a directory'); // запись в «каталог данных» невозможна
        $store = new JsonStore($dir . '/data', $dir . '/versions');
        $auth = new Auth($store, new RateLimiter($dir . '/rl', $this->clock()), $this->clock());
        $this->startSession();
        $oldLog = ini_set('error_log', $dir . '/php.log'); // сообщение о сбое записи — в сторону, не в вывод тестов

        try {
            $this->assertSame('invalid', @$auth->attempt('nobody', 'whatever-long-pass', '10.0.0.1'));
        } finally {
            ini_set('error_log', (string) $oldLog);
            @unlink($dir . '/php.log');
            @unlink($dir . '/data');
            foreach (glob($dir . '/rl/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir . '/rl');
            @rmdir($dir);
        }
    }

    public function test_admin_headers_also_on_staging_401_and_canonical_redirect(): void
    {
        $staging = TestApp::make(['env' => 'staging', 'appSecret' => str_repeat('s', 40)]);
        $res = $staging->handle(Request::create('GET', '/admin/'));
        $this->assertSame(401, $res->status());
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        $this->assertSame('noindex, nofollow', $res->header('X-Robots-Tag'));

        $www = TestApp::make(['baseUrl' => 'https://example.test']);
        $res = $www->handle(Request::create('GET', '/admin/login/', [], [], ['Host' => 'www.example.test']));
        $this->assertSame(301, $res->status());
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        $this->assertSame('noindex, nofollow', $res->header('X-Robots-Tag'));

        $public = $www->handle(Request::create('GET', '/booking/', [], [], ['Host' => 'www.example.test']));
        $this->assertStringNotContainsString('no-store', (string) $public->header('Cache-Control'));
    }

    public function test_too_long_new_password_rejected_with_message(): void
    {
        AdminHttp::login($this->app);
        $long = str_repeat('a', 1025);
        $res = AdminHttp::postWithToken($this->app, '/admin/password/', ['current' => AdminHttp::PASSWORD, 'new' => $long, 'repeat' => $long]);

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('слишком длинный', $res->body());
        $this->assertTrue(password_verify(AdminHttp::PASSWORD, (new Users($this->app->store()))->find('olga')['hash']));
    }

    public function test_anonymous_login_page_flood_is_limited_per_address_in_production(): void
    {
        $app = TestApp::make(['env' => 'prod', 'baseUrl' => 'https://grozacamp.ru', 'appSecret' => str_repeat('k', 40), 'fakeNow' => null]);
        $get = static fn (string $ip) => $app->handle(Request::create('GET', '/admin/login/', [], [], [], [], $ip, true));
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame(200, $get('203.0.113.9')->status());
        }
        $blocked = $get('203.0.113.9');
        $this->assertSame(429, $blocked->status());
        $this->assertSame('600', $blocked->header('Retry-After'));
        $this->assertSame(200, $get('203.0.113.10')->status()); // другой адрес не затронут
    }

    public function test_junk_session_cookie_does_not_bypass_the_new_session_limit(): void
    {
        $app = TestApp::make(['env' => 'prod', 'baseUrl' => 'https://grozacamp.ru', 'appSecret' => str_repeat('k', 40), 'fakeNow' => null]);
        $get = static fn (string $cookie) => $app->handle(Request::create('GET', '/admin/login/', [], [], ['Cookie' => 'groza_admin=' . $cookie], [], '203.0.113.9', true));
        for ($i = 0; $i < 20; $i++) {
            // каждый раз новое выдуманное значение — и правильного вида, и мусор
            $this->assertSame(200, $get($i % 2 === 0 ? bin2hex(random_bytes(16)) : 'junk' . $i)->status(), (string) $i);
        }
        $this->assertSame(429, $get(bin2hex(random_bytes(16)))->status());
        $this->assertSame(429, $get('x')->status());
    }

    public function test_live_session_cookie_is_not_counted_as_a_new_session(): void
    {
        $app = TestApp::make(['env' => 'prod', 'baseUrl' => 'https://grozacamp.ru', 'appSecret' => str_repeat('k', 40), 'fakeNow' => null]);
        $id = bin2hex(random_bytes(16));
        mkdir($app->config()['storageDir'] . '/sessions', 0777, true);
        touch($app->config()['storageDir'] . '/sessions/sess_' . $id);
        for ($i = 0; $i < 25; $i++) {
            $res = $app->handle(Request::create('GET', '/admin/login/', [], [], ['Cookie' => 'groza_admin=' . $id], [], '203.0.113.9', true));
            $this->assertSame(200, $res->status(), (string) $i);
        }
    }
}
