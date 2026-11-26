<?php
declare(strict_types=1);

namespace Groza\Admin;

use Groza\Data\ConflictException;
use Groza\Data\JsonStore;

/**
 * Учётные записи админки: storage/data/users.json → {"items":[{login,name,hash,createdAt}]}. Пароль хранится только как password_hash.
 * Каждое изменение — чтение, правка и запись с проверкой хеша файла (повтор при гонке): одновременные смена пароля,
 * перехеширование при входе и «user add» из консоли не затирают друг друга.
 */
final class Users
{
    private const RETRIES = 3;
    public const MIN_PASSWORD = 10;
    public const MAX_PASSWORD_BYTES = 1024;
    private const LOGIN = '/^[a-z0-9._-]{3,32}$/D';

    /** @param (\Closure():void)|null $beforeWrite только для тестов: вызывается между чтением и записью (имитация гонки) */
    public function __construct(private JsonStore $store, private ?\Closure $beforeWrite = null)
    {
    }

    public static function normalize(string $login): string
    {
        return mb_strtolower((string) preg_replace('/[\p{C}\p{Z}]+/u', '', $login));
    }

    public static function isValidLogin(string $login): bool
    {
        return preg_match(self::LOGIN, $login) === 1;
    }

    /** @return list<array{login:string,name:string,hash:string,createdAt:string}> */
    public function all(): array
    {
        $items = $this->store->read('users')['items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /** @return array{login:string,name:string,hash:string,createdAt:string}|null */
    public function find(string $login): ?array
    {
        $login = self::normalize($login);
        foreach ($this->all() as $item) {
            if (($item['login'] ?? null) === $login) {
                return $item;
            }
        }

        return null;
    }

    public function add(string $login, string $name, string $password, string $actor = 'system'): void
    {
        // регистр приводим к нижнему; пробел, перевод строки и невидимые символы — ошибка, а не молчаливая правка
        $login = mb_strtolower($login);
        if (!self::isValidLogin($login)) {
            throw new \InvalidArgumentException('Логин: 3–32 символа, латинские буквы, цифры, точка, дефис и подчёркивание.');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Имя: от 1 до 80 символов.');
        }
        self::assertPassword($password);
        $hash = password_hash($password, PASSWORD_DEFAULT); // долго — один раз, не на каждую попытку записи

        $this->mutate(static function (array $items) use ($login, $name, $hash): array {
            foreach ($items as $item) {
                if (($item['login'] ?? null) === $login) {
                    throw new \InvalidArgumentException("Пользователь «{$login}» уже есть.");
                }
            }
            $items[] = ['login' => $login, 'name' => $name, 'hash' => $hash, 'createdAt' => date('c')];

            return $items;
        }, $actor);
    }

    public function setPassword(string $login, string $password, string $actor = 'system'): void
    {
        self::assertPassword($password);
        $this->updateHash($login, password_hash($password, PASSWORD_DEFAULT), $actor);
    }

    /** Заменить готовый хеш (перехеширование при входе). Требований к длине нет: пароля здесь не видно. */
    public function updateHash(string $login, string $hash, string $actor = 'system'): void
    {
        $login = self::normalize($login);
        $this->mutate(static function (array $items) use ($login, $hash): array {
            $found = false;
            foreach ($items as &$item) {
                if (($item['login'] ?? null) === $login) {
                    $item['hash'] = $hash;
                    $found = true;
                }
            }
            unset($item);
            if (!$found) {
                throw new \InvalidArgumentException("Пользователя «{$login}» нет.");
            }

            return $items;
        }, $actor);
    }

    public function remove(string $login, string $actor = 'system'): void
    {
        $login = self::normalize($login);
        $this->mutate(static function (array $items) use ($login): array {
            $left = array_values(array_filter($items, static fn (array $i): bool => ($i['login'] ?? null) !== $login));
            if (count($left) === count($items)) {
                throw new \InvalidArgumentException("Пользователя «{$login}» нет.");
            }

            return $left;
        }, $actor);
    }

    public static function assertPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new \InvalidArgumentException('Пароль — не короче ' . self::MIN_PASSWORD . ' символов.');
        }
        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            throw new \InvalidArgumentException('Пароль слишком длинный: не больше ' . self::MAX_PASSWORD_BYTES . ' байт.');
        }
    }

    /**
     * Прочитать, изменить, записать с проверкой хеша; при гонке — заново на свежих данных (до RETRIES раз).
     *
     * @param callable(list<array<string,mixed>>):list<array<string,mixed>> $change
     * @throws ConflictException если файл меняют слишком часто
     */
    private function mutate(callable $change, string $actor): void
    {
        for ($try = 1; ; $try++) {
            $hash = $this->store->hash('users');
            $items = $change($this->all());
            if ($this->beforeWrite !== null) {
                ($this->beforeWrite)();
            }
            try {
                $this->store->write('users', ['items' => array_values($items)], $actor, $hash);

                return;
            } catch (ConflictException $e) {
                if ($try >= self::RETRIES) {
                    throw $e;
                }
            }
        }
    }
}
