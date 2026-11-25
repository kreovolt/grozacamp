<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Support;

use Groza\Support\Dates;
use PHPUnit\Framework\TestCase;

final class DatesTest extends TestCase
{
    public function test_ru(): void
    {
        $this->assertSame('2 июля 2027', Dates::ru('2027-07-02'));
        $this->assertSame('1 января 2027', Dates::ru('2027-01-01'));
    }

    public function test_range_same_month(): void
    {
        $this->assertSame("2\u{2013}22 июля 2027", Dates::range('2027-07-02', '2027-07-22'));
    }

    public function test_range_different_months(): void
    {
        $this->assertSame("25 июля \u{2013} 14 августа 2027", Dates::range('2027-07-25', '2027-08-14'));
    }

    public function test_range_different_years(): void
    {
        $this->assertSame("28 декабря 2026 \u{2013} 3 января 2027", Dates::range('2026-12-28', '2027-01-03'));
    }

    public function test_range_single_day(): void
    {
        $this->assertSame('2 июля 2027', Dates::range('2027-07-02', '2027-07-02'));
    }
}
