<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Forms;

use Groza\Forms\FormGuard;
use Groza\Http\Request;
use Groza\Support\FixedClock;
use Groza\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

final class FormGuardTest extends TestCase
{
    private const NOW = '2027-01-15T12:00:00+05:00';
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/groza-guard-' . bin2hex(random_bytes(5));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/ratelimit/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir . '/ratelimit');
        @rmdir($this->dir);
    }

    private function guard(string $iso = self::NOW): FormGuard
    {
        $clock = new FixedClock($iso);

        return new FormGuard('s3cret', new RateLimiter($this->dir . '/ratelimit', $clock, 's3cret'), $clock, 'https://grozacamp.ru');
    }

    /** Поля формы, показанной $secondsAgo секунд назад (относительно NOW). */
    private function post(int $secondsAgo, array $extra = []): array
    {
        $shown = (new \DateTimeImmutable(self::NOW))->modify("-{$secondsAgo} seconds");
        return $extra + $this->guard($shown->format(DATE_ATOM))->fields('booking') + ['parentName' => 'Анна'];
    }

    private function request(array $post, array $headers = [], string $ip = '203.0.113.5'): Request
    {
        return Request::create('POST', '/form/booking/', [], $post, $headers, [], $ip);
    }

    public function test_valid(): void
    {
        $this->assertNull($this->guard()->check($this->request($this->post(10)), 'booking'));
    }

    public function test_fields_contain_ts_and_signature(): void
    {
        $f = $this->guard()->fields('booking');
        $this->assertSame((string) (new \DateTimeImmutable(self::NOW))->getTimestamp(), $f['_ts']);
        $this->assertSame(hash_hmac('sha256', 'booking|' . $f['_ts'], 's3cret'), $f['_sig']);
    }

    public function test_honeypot(): void
    {
        $this->assertSame('honeypot', $this->guard()->check($this->request($this->post(10, [FormGuard::HONEYPOT => 'x'])), 'booking'));
    }

    public function test_too_fast(): void
    {
        $this->assertSame('too_fast', $this->guard()->check($this->request($this->post(1)), 'booking'));
    }

    public function test_expired(): void
    {
        $this->assertSame('expired', $this->guard()->check($this->request($this->post(3 * 3600)), 'booking'));
    }

    public function test_tampered_sig(): void
    {
        $post = $this->post(10);
        $post['_sig'] = strrev($post['_sig']);
        $this->assertSame('bad_signature', $this->guard()->check($this->request($post), 'booking'));
    }

    public function test_tampered_ts_and_missing_fields(): void
    {
        $post = $this->post(10);
        $post['_ts'] = (string) ((int) $post['_ts'] - 5000);
        $this->assertSame('bad_signature', $this->guard()->check($this->request($post), 'booking'));
        $this->assertSame('bad_signature', $this->guard()->check($this->request([]), 'booking'));
        $this->assertSame('bad_signature', $this->guard()->check($this->request(['_ts' => ['a'], '_sig' => ['b']]), 'booking'));
    }

    public function test_signature_is_bound_to_form_id(): void
    {
        $this->assertSame('bad_signature', $this->guard()->check($this->request($this->post(10)), 'vacancy'));
    }

    public function test_foreign_origin(): void
    {
        $r = $this->request($this->post(10), ['Origin' => 'https://evil.example']);
        $this->assertSame('bad_origin', $this->guard()->check($r, 'booking'));
        $r = $this->request($this->post(10), ['Origin' => 'null']);
        $this->assertSame('bad_origin', $this->guard()->check($r, 'booking'));
    }

    public function test_foreign_referer_when_no_origin(): void
    {
        $r = $this->request($this->post(10), ['Referer' => 'https://evil.example/page']);
        $this->assertSame('bad_origin', $this->guard()->check($r, 'booking'));
    }

    public function test_own_origin_or_referer_passes(): void
    {
        $r = $this->request($this->post(10), ['Origin' => 'https://grozacamp.ru']);
        $this->assertNull($this->guard()->check($r, 'booking'));
        $r = $this->request($this->post(10), ['Referer' => 'https://grozacamp.ru/booking/?shift=x']);
        $this->assertNull($this->guard()->check($r, 'booking'));
    }

    public function test_origin_wins_over_referer(): void
    {
        $r = $this->request($this->post(10), ['Origin' => 'https://evil.example', 'Referer' => 'https://grozacamp.ru/']);
        $this->assertSame('bad_origin', $this->guard()->check($r, 'booking'));
    }

    public function test_honeypot_name_means_nothing_to_autofill(): void
    {
        $this->assertDoesNotMatchRegularExpression('/web|site|url|name|mail|phone|tel|company|org|address/i', FormGuard::HONEYPOT);
    }

    public function test_rate_limit(): void
    {
        $guard = $this->guard();
        $this->assertGreaterThanOrEqual(10, FormGuard::RATE_MAX, 'за одним адресом мобильного оператора — много родителей');
        for ($i = 1; $i <= FormGuard::RATE_MAX; $i++) {
            $this->assertNull($guard->check($this->request($this->post(10)), 'booking'), "отправка $i");
        }
        $this->assertSame('rate_limited', $guard->check($this->request($this->post(10)), 'booking'));
        // другой IP не задет
        $this->assertNull($guard->check($this->request($this->post(10), [], '203.0.113.99'), 'booking'));
    }

    public function test_rejected_requests_do_not_consume_rate_limit(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < 8; $i++) {
            $this->assertSame('too_fast', $guard->check($this->request($this->post(1)), 'booking'));
        }
        $this->assertNull($guard->check($this->request($this->post(10)), 'booking'));
    }

    public function test_empty_secret_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $clock = new FixedClock(self::NOW);
        new FormGuard('', new RateLimiter($this->dir, $clock), $clock, 'https://grozacamp.ru');
    }
}
