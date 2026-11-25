<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Content;

use Groza\Content\PageData;
use PHPUnit\Framework\TestCase;

final class PageDataTest extends TestCase
{
    public function test_items_keeps_only_arrays(): void
    {
        $this->assertSame([['a' => 1]], PageData::items(['items' => [['a' => 1], 'x', 3]]));
        $this->assertSame([], PageData::items([]));
        $this->assertSame([], PageData::items(['items' => 'oops']));
    }

    public function test_lines_trims_and_drops_empty_and_non_strings(): void
    {
        $this->assertSame(['a', 'b'], PageData::lines([' a ', '', 5, null, 'b', '   ']));
        $this->assertSame([], PageData::lines('строка'));
        $this->assertSame([], PageData::lines(null));
    }

    public function test_pairs_split_on_first_bar(): void
    {
        $this->assertSame([['08:30', 'Подъём | и всё'], ['Без пары', '']], PageData::pairs(['08:30| Подъём | и всё', 'Без пары']));
    }

    public function test_included_reads_home_included(): void
    {
        $this->assertSame(['Питание'], PageData::included(['home.included' => ['Питание', ' '], 'x' => 1]));
        $this->assertSame([], PageData::included([]));
    }

    public function test_faq_sorted_limited_and_filtered(): void
    {
        $items = [
            ['question' => "B\n?", 'answer' => '<p>b</p>', 'order' => 20, 'onHome' => true],
            ['question' => 'A?', 'answer' => '<p>a</p>', 'order' => 10, 'onHome' => false],
            ['question' => 'C?', 'answer' => '<p>c</p>', 'order' => 30, 'onHome' => true],
        ];
        $this->assertSame(['A?', 'B ?', 'C?'], array_column(PageData::faq($items), 'question'));
        $this->assertSame(['B ?', 'C?'], array_column(PageData::faq($items, null, true), 'question'));
        $this->assertSame(['B ?'], array_column(PageData::faq($items, 1, true), 'question'));
        $this->assertSame(['question' => 'A?', 'answer' => '<p>a</p>'], PageData::faq($items)[0]);
    }

    public function test_text_returns_trimmed_string(): void
    {
        $this->assertSame('x', PageData::text([' k' => 1, 'k' => ' x '], 'k'));
        $this->assertSame('', PageData::text(['k' => ['a']], 'k'));
    }
}
