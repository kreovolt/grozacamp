<?php
declare(strict_types=1);

namespace Groza\Tests\Support;

use Groza\Admin\Users;
use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;

/** Помощник тестов админки: создать пользователя, войти, отправить POST с токеном. Сессия — одна на процесс (как cookie одного браузера). */
final class AdminHttp
{
    public const PASSWORD = 'correct-horse-9';

    public static function user(App $app, string $login = 'olga', string $name = 'Ольга', string $password = self::PASSWORD): void
    {
        (new Users($app->store()))->add($login, $name, $password);
    }

    public static function get(App $app, string $path, array $query = [], string $ip = '127.0.0.1'): Response
    {
        return $app->handle(Request::create('GET', $path, $query, [], [], [], $ip, true));
    }

    /** @param array<string,mixed> $post */
    public static function post(App $app, string $path, array $post = [], string $ip = '127.0.0.1', string $method = 'POST'): Response
    {
        return $app->handle(Request::create($method, $path, [], $post, [], [], $ip, true));
    }

    /** POST с действующим токеном сессии. @param array<string,mixed> $post */
    public static function postWithToken(App $app, string $path, array $post = [], string $ip = '127.0.0.1'): Response
    {
        return self::post($app, $path, ['_csrf' => Csrf::token()] + $post, $ip);
    }

    public static function login(App $app, string $login = 'olga', string $password = self::PASSWORD, string $ip = '127.0.0.1', string $next = ''): Response
    {
        self::get($app, '/admin/login/', [], $ip);

        return self::postWithToken($app, '/admin/login/', ['login' => $login, 'password' => $password, 'next' => $next], $ip);
    }
}
