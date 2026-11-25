<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Support;

use Groza\Support\FixedClock;
use Groza\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/groza-rl-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    public function test_count_and_reset(): void
    {
        $rl = new RateLimiter($this->dir, new FixedClock('2027-01-15T12:00:00+05:00'), 'k');
        $this->assertSame(0, $rl->count('b', 'x', 600));
        $rl->hit('b', 'x', 3, 600);
        $rl->hit('b', 'x', 3, 600);
        $this->assertSame(2, $rl->count('b', 'x', 600));
        $this->assertSame(2, $rl->count('b', 'x', 600), 'чтение не учитывает обращение');
        $later = new RateLimiter($this->dir, new FixedClock('2027-01-15T12:10:01+05:00'), 'k');
        $this->assertSame(0, $later->count('b', 'x', 600));
        $rl->reset('b', 'x');
        $this->assertSame(0, $rl->count('b', 'x', 600));
    }

    public function test_allows_up_to_max_then_blocks_and_window_expires(): void
    {
        $t0 = new RateLimiter($this->dir, new FixedClock('2027-01-15T12:00:00+05:00'), 'k');
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($t0->hit('b', '1.2.3.4', 3, 600));
        }
        $this->assertFalse($t0->hit('b', '1.2.3.4', 3, 600));
        $this->assertTrue($t0->hit('b', '5.6.7.8', 3, 600));
        $this->assertTrue($t0->hit('other', '1.2.3.4', 3, 600));

        $later = new RateLimiter($this->dir, new FixedClock('2027-01-15T12:10:01+05:00'), 'k');
        $this->assertTrue($later->hit('b', '1.2.3.4', 3, 600));
    }

    public function test_file_names_are_hashed_and_do_not_reveal_key(): void
    {
        $rl = new RateLimiter($this->dir, new FixedClock('2027-01-15T12:00:00+05:00'), 'k');
        $rl->hit('booking', '203.0.113.5', 3, 600);
        $files = glob($this->dir . '/*') ?: [];
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}/', basename($files[0]));
        $this->assertStringNotContainsString('203.0.113.5', (string) file_get_contents($files[0]));
        $this->assertStringContainsString(hash('sha256', 'k' . 'booking' . '|' . '203.0.113.5'), basename($files[0]));
    }

    public function test_expired_entries_are_pruned(): void
    {
        $old = new RateLimiter($this->dir, new FixedClock('2027-01-15T12:00:00+05:00'), 'k');
        $old->hit('b', 'old', 3, 600);
        $this->assertCount(1, glob($this->dir . '/*'));
        $new = new RateLimiter($this->dir, new FixedClock('2027-01-15T14:00:00+05:00'), 'k', 1);
        $new->hit('b', 'fresh', 3, 600);
        $this->assertCount(1, glob($this->dir . '/*'));
    }
}
