<?php
declare(strict_types=1);

namespace Groza\Admin;

use Groza\Data\JsonStore;

/** Учётные записи админки: storage/data/users.json → {"items":[{login,name,hash,createdAt}]}. Пароль хранится только как password_hash. */
final class Users
{
    public const MIN_PASSWORD = 10;
    private const LOGIN = '/^[a-z0-9._-]{3,32}$/D';

    public function __construct(private JsonStore $store)
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

        $items = $this->all();
        if ($this->find($login) !== null) {
            throw new \InvalidArgumentException("Пользователь «{$login}» уже есть.");
        }
        $items[] = [
            'login' => $login,
            'name' => $name,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'createdAt' => date('c'),
        ];
        $this->save($items, $actor);
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
        $items = $this->all();
        $found = false;
        foreach ($items as &$item) {
            if ($item['login'] === $login) {
                $item['hash'] = $hash;
                $found = true;
            }
        }
        unset($item);
        if (!$found) {
            throw new \InvalidArgumentException("Пользователя «{$login}» нет.");
        }
        $this->save($items, $actor);
    }

    public function remove(string $login, string $actor = 'system'): void
    {
        $login = self::normalize($login);
        $items = array_values(array_filter($this->all(), static fn (array $i): bool => $i['login'] !== $login));
        if (count($items) === count($this->all())) {
            throw new \InvalidArgumentException("Пользователя «{$login}» нет.");
        }
        $this->save($items, $actor);
    }

    public static function assertPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new \InvalidArgumentException('Пароль — не короче ' . self::MIN_PASSWORD . ' символов.');
        }
    }

    /** @param list<array<string,string>> $items */
    private function save(array $items, string $actor): void
    {
        $this->store->write('users', ['items' => $items], $actor);
    }
}
