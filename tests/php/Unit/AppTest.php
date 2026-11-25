<?php
declare(strict_types=1);

namespace Groza\Tests\Unit;

use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    public function test_home_renders_site_layout(): void
    {
        $res = TestApp::get(TestApp::make(), '/');

        $this->assertSame(200, $res->status());
        $this->assertSame('text/html; charset=utf-8', $res->header('Content-Type'));
        $this->assertStringStartsWith('<!doctype html>', ltrim($res->body()));
    }

    public function test_test_app_uses_fixed_clock_in_camp_timezone(): void
    {
        $now = TestApp::make()->clock()->now();

        $this->assertSame('2027-01-15 12:00:00', $now->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Yekaterinburg', $now->getTimezone()->getName());
    }

    public function test_fake_now_ignored_outside_test_env(): void
    {
        $app = TestApp::make(['env' => 'dev']);

        $this->assertNotSame('2027-01-15', $app->clock()->now()->format('Y-m-d'));
    }
}
