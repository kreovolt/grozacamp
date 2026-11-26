<?php
declare(strict_types=1);

namespace Groza\Support;

/**
 * Сессия админки. Стандартный обработчик PHP, но: своё имя, cookie только на /admin/ (HttpOnly, SameSite=Strict,
 * Secure вне dev|test), строгий режим (чужой идентификатор не принимается), файлы в {storageDir}/sessions вне корня сайта.
 * Публичные страницы сессию не запускают. Время жизни (бездействие, предел) хранится в данных сессии — см. Auth.
 *
 * В CLI (тесты) вместо PHP-сессии работает копия в памяти процесса: она же играет роль «cookie одного браузера».
 */
final class Session
{
    public const NAME = 'groza_admin';
    private const GC_LIFETIME = 43200 + 3600; // предел сессии + запас: файлы старше уходят сами

    private static bool $memActive = false;
    private static string $memId = '';
    /** @var array<string,mixed> */
    private static array $memData = [];

    private static function memory(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /** @param array<string,mixed> $config env, storageDir @return array{lifetime:int,path:string,secure:bool,httponly:bool,samesite:string} */
    public static function cookieParams(array $config): array
    {
        return [
            'lifetime' => 0,
            'path' => '/admin/',
            'secure' => !in_array((string) ($config['env'] ?? 'prod'), ['dev', 'test'], true),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    /** Идентификатор в cookie: знаки и длина, которые выдаёт PHP (sid_bits_per_character 4–6, длина 22–256). */
    private const ID_FORMAT = '/^[A-Za-z0-9,-]{22,256}$/D';

    /**
     * Нужна ли сессия: есть cookie с ЖИВОЙ сессией, это страница входа (выдаёт токен) или тесты.
     * Мусорное или устаревшее значение cookie сессию не запускает: иначе строгий режим заводил бы на каждый такой запрос
     * новый файл в storage/sessions (засорение диска без входа).
     *
     * @param array<string,mixed> $config storageDir
     */
    public static function wanted(?string $cookieHeader, bool $issueNew, array $config): bool
    {
        return self::memory() || $issueNew || self::hasCookie($cookieHeader, $config);
    }

    /**
     * Пришла ли cookie сессии, у которой на сервере есть файл. Значение не того вида или без файла — как будто cookie нет
     * (анонимный запрос: на странице входа его считает ограничитель новых сессий).
     *
     * @param array<string,mixed> $config storageDir
     */
    public static function hasCookie(?string $cookieHeader, array $config): bool
    {
        $id = self::cookieId($cookieHeader);

        return $id !== null && is_file(self::dir($config) . '/sess_' . $id);
    }

    /** Значение cookie сессии, если оно похоже на идентификатор PHP; иначе null. */
    public static function cookieId(?string $cookieHeader): ?string
    {
        foreach (explode(';', (string) $cookieHeader) as $pair) {
            $parts = explode('=', trim($pair), 2);
            if ($parts[0] === self::NAME) {
                $v = $parts[1] ?? '';

                return preg_match(self::ID_FORMAT, $v) === 1 ? $v : null;
            }
        }

        return null;
    }

    /** Папка файлов сессий: {storageDir}/sessions (вне корня сайта). @param array<string,mixed> $config */
    public static function dir(array $config): string
    {
        return rtrim((string) ($config['storageDir'] ?? ''), '/\\') . '/sessions';
    }

    /** @param array<string,mixed> $config env, storageDir */
    public static function start(array $config): void
    {
        if (self::isActive()) {
            return;
        }
        if (self::memory()) {
            self::$memActive = true;
            self::$memId = self::$memId !== '' ? self::$memId : bin2hex(random_bytes(16));

            return;
        }
        $dir = self::dir($config);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Не удаётся создать каталог сессий');
        }
        foreach ([
            'session.use_strict_mode' => '1', 'session.use_only_cookies' => '1', 'session.use_cookies' => '1',
            'session.use_trans_sid' => '0', 'session.cache_limiter' => '', // заголовки кэша задаёт App
            'session.gc_maxlifetime' => (string) self::GC_LIFETIME, 'session.gc_probability' => '1', 'session.gc_divisor' => '100',
        ] as $k => $v) {
            ini_set($k, $v);
        }
        session_name(self::NAME);
        session_save_path($dir);
        session_set_cookie_params(self::cookieParams($config));
        if (!session_start()) {
            throw new \RuntimeException('Не удаётся запустить сессию');
        }
    }

    public static function isActive(): bool
    {
        return self::memory() ? self::$memActive : session_status() === PHP_SESSION_ACTIVE;
    }

    public static function id(): string
    {
        return self::memory() ? self::$memId : (string) session_id();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::isActive()) {
            return $default;
        }

        return (self::memory() ? self::$memData : $_SESSION)[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (!self::isActive()) {
            throw new \LogicException('Сессия не запущена');
        }
        if (self::memory()) {
            self::$memData[$key] = $value;
        } else {
            $_SESSION[$key] = $value;
        }
    }

    public static function forget(string $key): void
    {
        if (!self::isActive()) {
            return;
        }
        if (self::memory()) {
            unset(self::$memData[$key]);
        } else {
            unset($_SESSION[$key]);
        }
    }

    /** Новый идентификатор с теми же данными; старый файл удаляется (против фиксации сессии). */
    public static function regenerate(): void
    {
        if (!self::isActive()) {
            return;
        }
        if (self::memory()) {
            self::$memId = bin2hex(random_bytes(16));
        } else {
            session_regenerate_id(true);
        }
    }

    /** Данные стираются, идентификатор новый: страница, которая выводится сразу после, получит свежую пустую сессию. */
    public static function destroy(): void
    {
        if (!self::isActive()) {
            return;
        }
        if (self::memory()) {
            self::$memData = [];
        } else {
            $_SESSION = [];
        }
        self::regenerate();
    }

    /** Записать и закрыть (блокировка файла сессии не должна держаться дольше запроса). */
    public static function close(): void
    {
        if (!self::memory() && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /** Для тестов: как будто браузер без cookie. */
    public static function reset(): void
    {
        self::$memActive = false;
        self::$memId = '';
        self::$memData = [];
    }
}
