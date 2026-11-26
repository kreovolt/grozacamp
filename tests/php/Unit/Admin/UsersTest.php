<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\Users;
use Groza\Data\JsonStore;
use PHPUnit\Framework\TestCase;

final class UsersTest extends TestCase
{
    private string $dir;
    private JsonStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/groza-users-' . bin2hex(random_bytes(5));
        $this->store = new JsonStore($this->dir . '/data', $this->dir . '/versions');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . '/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->dir);
    }

    public function test_concurrent_changes_do_not_overwrite_each_other(): void
    {
        $users = new Users($this->store);
        $users->add('olga', 'Ольга', 'long-enough-pass');
        $raced = false;
        // пока меняем пароль Ольги, в консоли добавили пользователя (одна гонка — потом запись проходит)
        $racing = new Users($this->store, function () use (&$raced, $users): void {
            if (!$raced) {
                $raced = true;
                $users->add('ivan', 'Иван', 'another-long-pass');
            }
        });
        $racing->setPassword('olga', 'brand-new-password');

        $this->assertTrue($raced);
        $this->assertNotNull($users->find('ivan'), 'добавленный из консоли не потерян');
        $this->assertTrue(password_verify('brand-new-password', (string) $users->find('olga')['hash']), 'и смена пароля записана');
    }

    public function test_writer_gives_up_after_repeated_conflicts(): void
    {
        $users = new Users($this->store);
        $users->add('olga', 'Ольга', 'long-enough-pass');
        $n = 0;
        $always = new Users($this->store, function () use (&$n): void {
            $n++;
            $this->store->write('users', array_replace($this->store->read('users'), ['touch' => $n]), 'test'); // кто-то пишет каждый раз
        });

        $this->expectException(\Groza\Data\ConflictException::class);
        try {
            $always->updateHash('olga', 'x');
        } finally {
            $this->assertSame(3, $n);
        }
    }

    public function test_add_stores_hash_not_password_in_documented_shape(): void
    {
        (new Users($this->store))->add('Olga.Office', 'Ольга', 'long-enough-pass');

        $item = $this->store->read('users')['items'][0];
        $this->assertSame(['login', 'name', 'hash', 'createdAt'], array_keys($item));
        $this->assertSame('olga.office', $item['login']);
        $this->assertSame('Ольга', $item['name']);
        $this->assertTrue(password_verify('long-enough-pass', $item['hash']));
        $this->assertStringNotContainsString('long-enough-pass', (string) file_get_contents($this->dir . '/data/users.json'));
    }

    public function test_users_short_password_rejected(): void
    {
        $users = new Users($this->store);
        $this->expectException(\InvalidArgumentException::class);
        $users->add('olga', 'Ольга', '123456789');
    }

    public function test_password_longer_than_1024_bytes_rejected(): void
    {
        $users = new Users($this->store);
        $users->add('olga', 'Ольга', str_repeat('a', 1024));
        $this->expectException(\InvalidArgumentException::class);
        $users->setPassword('olga', str_repeat('a', 1025));
    }

    public function test_set_password_rejects_short_and_keeps_old_hash(): void
    {
        $users = new Users($this->store);
        $users->add('olga', 'Ольга', 'long-enough-pass');
        try {
            $users->setPassword('olga', 'short');
            $this->fail('short password accepted');
        } catch (\InvalidArgumentException) {
        }
        $this->assertTrue(password_verify('long-enough-pass', $users->find('olga')['hash']));

        $users->setPassword('OLGA', 'another-long-pass');
        $this->assertTrue(password_verify('another-long-pass', $users->find('olga')['hash']));
    }

    /** @dataProvider badLogins */
    public function test_bad_logins_rejected(string $login): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Users($this->store))->add($login, 'X', 'long-enough-pass');
    }

    /** @return array<string,array{string}> */
    public static function badLogins(): array
    {
        return [
            'short' => ['ab'], 'long' => [str_repeat('a', 33)], 'space' => ['ol ga'], 'cyrillic' => ['ольга'],
            'slash' => ['a/b/c'], 'empty' => [''], 'newline' => ["olga\n"],
        ];
    }

    public function test_duplicate_login_rejected_case_insensitively(): void
    {
        $users = new Users($this->store);
        $users->add('olga', 'Ольга', 'long-enough-pass');
        $this->expectException(\InvalidArgumentException::class);
        $users->add('OLGA', 'Другая', 'long-enough-pass');
    }

    public function test_remove_and_unknown_login(): void
    {
        $users = new Users($this->store);
        $users->add('olga', 'Ольга', 'long-enough-pass');
        $users->add('petr', 'Пётр', 'long-enough-pass');
        $users->remove('olga');

        $this->assertNull($users->find('olga'));
        $this->assertNotNull($users->find('petr'));
        $this->expectException(\InvalidArgumentException::class);
        $users->remove('nobody');
    }
}
