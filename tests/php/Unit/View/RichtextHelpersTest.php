<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Groza\View\richtext;
use function Groza\View\safe_url;

final class RichtextHelpersTest extends TestCase
{
    public function test_richtext_cleans(): void
    {
        $this->assertSame('<p>x</p>', richtext('<p onclick="a()">x</p><script>alert(1)</script>'));
        $this->assertSame('', richtext(''));
        $this->assertSame(richtext('<p>a</p>'), richtext('<p>a</p>'), 'повторный вызов (кэш) даёт то же');
    }

    /** @return array<string,array{?string,string}> */
    public static function urls(): array
    {
        return [
            'https' => ['https://vk.com/grozacamp', 'https://vk.com/grozacamp'],
            'http' => ['http://example.ru/a?b=1', 'http://example.ru/a?b=1'],
            'trimmed' => ['  https://vk.com/x  ', 'https://vk.com/x'],
            'upper scheme' => ['HTTPS://vk.com/x', 'HTTPS://vk.com/x'],
            'javascript' => ['javascript:alert(1)', ''],
            'data' => ['data:text/html,<script>1</script>', ''],
            'mailto' => ['mailto:a@b.ru', ''],
            'relative' => ['/admin/', ''],
            'protocol relative' => ['//evil.example', ''],
            'no host' => ['https://', ''],
            'tab in scheme' => ["java\tscript:alert(1)", ''],
            'space in url' => ['https://a.ru/x y', ''],
            'quote' => ['https://a.ru/"onmouseover="x', ''],
            'null' => [null, ''],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('urls')]
    public function test_safe_url(?string $in, string $expected): void
    {
        $this->assertSame($expected, safe_url($in));
    }
}
