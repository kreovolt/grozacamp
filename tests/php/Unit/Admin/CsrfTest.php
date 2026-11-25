<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Support\Csrf;
use Groza\Support\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        Session::reset();
        Session::start(['env' => 'test', 'storageDir' => sys_get_temp_dir()]);
    }

    protected function tearDown(): void
    {
        Session::reset();
    }

    public function test_token_is_random_hex_and_stable_within_session(): void
    {
        $token = Csrf::token();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame($token, Csrf::token());
    }

    public function test_verify_accepts_only_the_session_token(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::verify($token));
        $this->assertFalse(Csrf::verify(null));
        $this->assertFalse(Csrf::verify(''));
        $this->assertFalse(Csrf::verify($token . 'x'));
        $this->assertFalse(Csrf::verify(strtoupper($token)));
        $this->assertFalse(Csrf::verify(str_repeat('0', 64)));
    }

    public function test_verify_fails_without_a_token_issued_in_the_session(): void
    {
        $this->assertFalse(Csrf::verify(str_repeat('a', 64)));
        $this->assertFalse(Csrf::verify(''));
    }

    public function test_token_belongs_to_the_session(): void
    {
        $first = Csrf::token();
        Session::reset();
        Session::start(['env' => 'test', 'storageDir' => sys_get_temp_dir()]);

        $this->assertFalse(Csrf::verify($first));
        $this->assertNotSame($first, Csrf::token());
    }

    public function test_rotate_invalidates_old_token(): void
    {
        $old = Csrf::token();
        Csrf::rotate();

        $this->assertFalse(Csrf::verify($old));
        $this->assertNotSame($old, Csrf::token());
    }
}
