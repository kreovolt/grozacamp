<?php
declare(strict_types=1);

namespace Groza\Admin;

use Groza\Data\ConflictException;
use Groza\Data\JsonStore;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Clock;
use Groza\Support\Csrf;
use Groza\Support\RateLimiter;
use Groza\Support\Session;

/**
 * Вход в админку.
 *  - Защита от перебора: сбои считаются отдельно по логину (в нижнем регистре, без пробелов) и по IP;
 *    maxFailures сбоев за lockSeconds — блокировка этого ключа на lockSeconds. Верный пароль при блокировке не проверяется.
 *  - Неизвестный логин проверяется против заранее посчитанного хеша того же алгоритма (одинаковое время ответа).
 *  - После входа и смены пароля идентификатор сессии и токен CSRF новые. Бездействие и предельный срок считаются по данным
 *    сессии. Сессия умирает и при удалении пользователя или смене его пароля в другом месте (отпечаток хеша).
 */
final class Auth
{
    private const MAX_PASSWORD_BYTES = 1024;
    private const FAIL_USER = 'login-user';
    private const FAIL_IP = 'login-ip';
    private const LOCK_USER = 'login-user-lock';
    private const LOCK_IP = 'login-ip-lock';
    private const FAIL_PASSWORD = 'password-change';

    private Users $users;

    public function __construct(
        private JsonStore $store,
        private RateLimiter $limiter,
        private Clock $clock,
        private int $idleSeconds = 1800,
        private int $absoluteSeconds = 43200,
        private int $maxFailures = 5,
        private int $lockSeconds = 900,
    ) {
        $this->users = new Users($store);
    }

    /** @return 'ok'|'invalid'|'locked' */
    public function attempt(string $login, string $password, string $ip): string
    {
        $key = self::key($login);
        if ($this->isLocked(self::LOCK_USER, $key) || $this->isLocked(self::LOCK_IP, $ip)) {
            return 'locked';
        }

        $normalized = Users::normalize($login);
        $user = Users::isValidLogin($normalized) ? $this->users->find($normalized) : null;
        $candidate = strlen($password) <= self::MAX_PASSWORD_BYTES ? $password : '';
        // password_verify выполняется всегда — и для неизвестного логина, — чтобы время ответа не выдавало, есть ли такой пользователь
        $matches = password_verify($candidate, $user['hash'] ?? $this->dummyHash());
        if ($user === null || !$matches || $candidate === '') {
            $this->fail(self::FAIL_USER, self::LOCK_USER, $key);
            $this->fail(self::FAIL_IP, self::LOCK_IP, $ip);

            return 'invalid';
        }

        $this->limiter->reset(self::FAIL_USER, $key);
        if (password_needs_rehash($user['hash'], PASSWORD_DEFAULT)) {
            $user = $this->rehash($user, $candidate);
        }
        Session::regenerate();
        Csrf::rotate();
        $now = $this->clock->now()->getTimestamp();
        Session::set('uid', $user['login']);
        Session::set('sig', self::fingerprint($user['hash']));
        Session::set('created', $now);
        Session::set('seen', $now);

        return 'ok';
    }

    /** @return array{login:string,name:string}|null */
    public function user(): ?array
    {
        $uid = Session::get('uid');
        if (!is_string($uid)) {
            return null;
        }
        $now = $this->clock->now()->getTimestamp();
        $seen = (int) Session::get('seen', 0);
        $created = (int) Session::get('created', 0);
        $row = $this->users->find($uid);
        $sig = Session::get('sig');
        if (
            $now - $seen > $this->idleSeconds || $now - $created > $this->absoluteSeconds
            || $row === null || !is_string($sig) || !hash_equals(self::fingerprint($row['hash']), $sig)
        ) {
            Session::destroy();

            return null;
        }
        Session::set('seen', $now);

        return ['login' => $row['login'], 'name' => $row['name']];
    }

    public function logout(): void
    {
        Session::destroy();
    }

    /** null — пользователь вошёл; иначе переадресация на вход. GET запоминает адрес в next, POST возвращает на вход без него. */
    public function requireUser(Request $r): ?Response
    {
        if ($this->user() !== null) {
            return null;
        }
        $to = '/admin/login/';
        if ($r->method() === 'GET' || $r->method() === 'HEAD') {
            $query = $r->queryString();
            $to .= '?next=' . rawurlencode(self::safeNext($r->path() . ($query !== '' ? '?' . $query : '')));
        }

        return Response::redirect($to);
    }

    /** Переход после входа — только внутри /admin/ (без «//», обратных косых, управляющих символов, «..» и повторного входа). */
    public static function safeNext(mixed $next): string
    {
        $default = '/admin/';
        if (!is_string($next) || strlen($next) > 500 || !str_starts_with($next, '/admin/')) {
            return $default;
        }
        if (preg_match('/[\x00-\x20\x7f\\\\]|%(?:2e|2f|5c|0d|0a|00)/i', $next) === 1 || str_contains($next, '..') || str_starts_with($next, '/admin/login/')) {
            return $default;
        }

        return $next;
    }

    /** @return 'ok'|'wrong'|'short'|'mismatch'|'same'|'locked'|'anonymous' */
    public function changePassword(string $current, string $new, string $repeat): string
    {
        $me = $this->user();
        if ($me === null) {
            return 'anonymous';
        }
        $key = self::key($me['login']);
        if ($this->limiter->count(self::FAIL_PASSWORD, $key, $this->lockSeconds) >= $this->maxFailures) {
            return 'locked';
        }
        $row = $this->users->find($me['login']);
        if ($row === null || strlen($current) > self::MAX_PASSWORD_BYTES || !password_verify($current, $row['hash'])) {
            $this->limiter->hit(self::FAIL_PASSWORD, $key, $this->maxFailures, $this->lockSeconds);

            return 'wrong';
        }
        if (mb_strlen($new) < Users::MIN_PASSWORD) {
            return 'short';
        }
        if ($new !== $repeat) {
            return 'mismatch';
        }
        if ($new === $current) {
            return 'same';
        }

        $this->users->setPassword($me['login'], $new, $me['login']);
        $this->limiter->reset(self::FAIL_PASSWORD, $key);
        Session::set('sig', self::fingerprint($this->users->find($me['login'])['hash'] ?? ''));
        Session::regenerate();
        Csrf::rotate();

        return 'ok';
    }

    /** Ключ журнала для неудачного входа: 12 символов sha256 от логина — не сам логин. */
    public static function auditKey(string $login): string
    {
        return substr(hash('sha256', Users::normalize($login)), 0, 12);
    }

    private static function key(string $login): string
    {
        return mb_substr(Users::normalize($login), 0, 64);
    }

    private static function fingerprint(string $hash): string
    {
        return substr(hash('sha256', $hash), 0, 20);
    }

    private function isLocked(string $lockBucket, string $key): bool
    {
        return $this->limiter->count($lockBucket, $key, $this->lockSeconds) >= 1;
    }

    /** Учесть сбой; на последнем допустимом ставится метка блокировки — она держится lockSeconds с этого момента. */
    private function fail(string $failBucket, string $lockBucket, string $key): void
    {
        $this->limiter->hit($failBucket, $key, $this->maxFailures, $this->lockSeconds);
        if ($this->limiter->count($failBucket, $key, $this->lockSeconds) >= $this->maxFailures) {
            $this->limiter->hit($lockBucket, $key, 1, $this->lockSeconds);
        }
    }

    /** @param array{login:string,name:string,hash:string,createdAt:string} $user @return array{login:string,name:string,hash:string,createdAt:string} */
    private function rehash(array $user, string $password): array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $this->users->updateHash($user['login'], $hash, $user['login']);
            $user['hash'] = $hash;
        } catch (ConflictException | \RuntimeException | \InvalidArgumentException) {
            // не вышло сейчас — повторится при следующем входе; сам вход не страдает
        }

        return $user;
    }

    /** Хеш несуществующего пользователя: считается один раз за установку (того же алгоритма и стоимости, что у настоящих). */
    private function dummyHash(): string
    {
        $meta = $this->store->read('auth-meta');
        $dummy = $meta['dummy'] ?? null;
        if (!is_string($dummy) || password_get_info($dummy)['algo'] === null || password_needs_rehash($dummy, PASSWORD_DEFAULT)) {
            $dummy = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $this->store->write('auth-meta', ['dummy' => $dummy], 'system');
        }

        return $dummy;
    }
}
