<?php
declare(strict_types=1);

namespace Groza\Support;

/** CSRF-токен, привязанный к сессии. Поле формы — `_csrf`. Страница входа получает токен в той же (ещё пустой) сессии. */
final class Csrf
{
    public const FIELD = '_csrf';
    private const KEY = 'csrf';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }

        return $token;
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get(self::KEY);

        return is_string($expected) && $expected !== '' && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    /** Новый токен (после входа и смены пароля). */
    public static function rotate(): void
    {
        Session::forget(self::KEY);
        self::token();
    }
}
