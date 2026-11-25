<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\Admin\Auth;
use Groza\Admin\Users;
use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;

/** Вход, выход, смена пароля и стартовая страница админки. CSRF для POST проверяет App до маршрутизации. */
final class AuthController
{
    public const MSG_INVALID = 'Неверный логин или пароль';
    public const MSG_LOCKED = 'Слишком много попыток. Попробуйте через 15 минут.';

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function showLogin(Request $r, array $params): Response
    {
        $next = Auth::safeNext($r->query('next'));
        if ($this->app->auth()->user() !== null) {
            return Response::redirect($next);
        }

        return $this->page('admin/login', ['login' => '', 'next' => $next, 'error' => null]);
    }

    /** @param array<string,string> $params */
    public function login(Request $r, array $params): Response
    {
        $login = self::text($r->post('login'));
        $next = Auth::safeNext($r->post('next'));
        $audit = $this->app->audit();

        $result = $this->app->auth()->attempt($login, self::text($r->post('password')), $r->ip());
        if ($result === 'ok') {
            $audit->record(Users::normalize($login), 'auth.login.ok', '-');

            return Response::redirect($next);
        }
        if ($result === 'invalid') {
            $audit->record('-', 'auth.login.fail', Auth::auditKey($login));

            return $this->page('admin/login', ['login' => mb_substr($login, 0, 64), 'next' => $next, 'error' => self::MSG_INVALID]);
        }

        // Блокировка: записи в журнал не пишутся (иначе перебор раздувал бы журнал); сами сбои уже записаны выше
        return $this->page('admin/login', ['login' => mb_substr($login, 0, 64), 'next' => $next, 'error' => self::MSG_LOCKED], 429)
            ->withHeader('Retry-After', '900');
    }

    /** @param array<string,string> $params */
    public function logout(Request $r, array $params): Response
    {
        $me = $this->app->auth()->user();
        $this->app->auth()->logout();
        if ($me !== null) {
            $this->app->audit()->record($me['login'], 'auth.logout', '-');
        }

        return Response::redirect('/admin/login/');
    }

    /** @param array<string,string> $params */
    public function home(Request $r, array $params): Response
    {
        return $this->page('admin/home', []);
    }

    /** @param array<string,string> $params */
    public function showPassword(Request $r, array $params): Response
    {
        return $this->page('admin/password', ['error' => null, 'done' => $r->query('ok') === '1']);
    }

    /** @param array<string,string> $params */
    public function changePassword(Request $r, array $params): Response
    {
        $me = $this->app->auth()->user();
        $result = $this->app->auth()->changePassword(
            self::text($r->post('current')),
            self::text($r->post('new')),
            self::text($r->post('repeat'))
        );
        if ($result === 'ok') {
            $this->app->audit()->record((string) $me['login'], 'auth.password.change', '-');

            return Response::redirect('/admin/password/?ok=1');
        }
        $messages = [
            'wrong' => 'Текущий пароль указан неверно.',
            'short' => 'Новый пароль — не короче ' . Users::MIN_PASSWORD . ' символов.',
            'mismatch' => 'Пароли не совпадают.',
            'same' => 'Новый пароль должен отличаться от текущего.',
            'locked' => 'Слишком много попыток. Попробуйте через 15 минут.',
            'anonymous' => 'Сессия устарела. Войдите снова.',
        ];

        return $this->page('admin/password', ['error' => $messages[$result] ?? $messages['wrong'], 'done' => false], $result === 'locked' ? 429 : 200);
    }

    /** @param array<string,mixed> $vars */
    private function page(string $template, array $vars, int $status = 200): Response
    {
        $vars += ['user' => $this->app->auth()->user(), 'csrf' => Csrf::token()];

        return Response::html($this->app->view()->render($template, $vars), $status);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
