<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Support\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        Session::reset();
    }

    protected function tearDown(): void
    {
        Session::reset();
    }

    public function test_cookie_params_are_hardened(): void
    {
        $p = Session::cookieParams(['env' => 'prod']);

        $this->assertSame(['lifetime' => 0, 'path' => '/admin/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict'], $p);
        $this->assertSame('groza_admin', Session::NAME);
    }

    public function test_secure_flag_only_off_in_dev_and_test(): void
    {
        foreach (['dev', 'test'] as $env) {
            $this->assertFalse(Session::cookieParams(['env' => $env])['secure'], $env);
        }
        foreach (['staging', 'prod', 'anything'] as $env) {
            $this->assertTrue(Session::cookieParams(['env' => $env])['secure'], $env);
        }
    }

    public function test_get_set_forget_and_inactive_session(): void
    {
        $this->assertFalse(Session::isActive());
        $this->assertSame('d', Session::get('a', 'd'));

        Session::start(['env' => 'test', 'storageDir' => sys_get_temp_dir()]);
        $this->assertTrue(Session::isActive());
        Session::set('a', 1);
        $this->assertSame(1, Session::get('a'));
        Session::forget('a');
        $this->assertNull(Session::get('a'));
    }

    public function test_regenerate_changes_id_keeps_data_destroy_clears_both(): void
    {
        Session::start(['env' => 'test', 'storageDir' => sys_get_temp_dir()]);
        Session::set('a', 1);
        $id = Session::id();

        Session::regenerate();
        $this->assertNotSame($id, Session::id());
        $this->assertSame(1, Session::get('a'));

        $id = Session::id();
        Session::destroy();
        $this->assertNotSame($id, Session::id());
        $this->assertNull(Session::get('a'));
    }

    public function test_cookie_name_detection(): void
    {
        $this->assertTrue(Session::hasCookie('foo=1; groza_admin=abc'));
        $this->assertFalse(Session::hasCookie('foo=1; groza_admin2=abc'));
        $this->assertFalse(Session::hasCookie(null));
        $this->assertFalse(Session::hasCookie('groza_admin='));
    }
}
