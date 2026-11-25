<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Support;

use Groza\Support\Phone;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    public function test_normalizes_any_russian_format(): void
    {
        foreach (['8 950 441-12-99', '+7(950)4411299', '9504411299', '8-950-441-12-99', '+7 (950) 441-12-99', '7 950 441 12 99'] as $raw) {
            $this->assertSame('+79504411299', Phone::normalize($raw), $raw);
        }
    }

    public function test_accepts_dot_and_slash_separators(): void
    {
        foreach (['8.950.441.12.99', '8/950/441/12/99', '+7.950.441.12.99', '950.441.12.99'] as $raw) {
            $this->assertSame('+79504411299', Phone::normalize($raw), $raw);
        }
    }

    public function test_rejects_non_russian_or_malformed(): void
    {
        foreach (['12345', '', '+1 650 441 12 99', '+3 950 441-12-99', '99504411299', '89504411299 0', '+8 950 441-12-99', '+89504411299', '+9504411299', '8+9504411299', '+', '++79504411299'] as $raw) {
            $this->assertNull(Phone::normalize($raw), $raw);
        }
    }

    public function test_pretty(): void
    {
        $this->assertSame('+7 (950) 441-12-99', Phone::pretty('+79504411299'));
    }
}
