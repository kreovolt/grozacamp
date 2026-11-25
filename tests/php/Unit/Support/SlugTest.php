<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Support;

use Groza\Support\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function test_transliterates_gost_b(): void
    {
        $this->assertSame('pogranichnik', Slug::fromRu('Пограничник'));
        $this->assertSame('spasatel', Slug::fromRu('Спасатель'));
        $this->assertSame('leto-2027', Slug::fromRu('Лето 2027'));
        $this->assertSame('shtormovik-yozh-czvet', Slug::fromRu('  Штормовик, ёж: цвет!! '));
        $this->assertSame('shhuka-chaj', Slug::fromRu('Щука-чай'));
    }

    public function test_keeps_existing_slug_and_empty_input(): void
    {
        $this->assertSame('base-cabins', Slug::fromRu('base-cabins'));
        $this->assertSame('', Slug::fromRu('!!!'));
    }
}
