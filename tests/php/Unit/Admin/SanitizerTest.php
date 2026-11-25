<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    public function test_strips_attributes_and_scripts(): void
    {
        $this->assertSame('<p>x</p>', Sanitizer::clean('<p onclick="a()">x</p><script>alert(1)</script>'));
    }

    public function test_javascript_href_removed(): void
    {
        $this->assertStringNotContainsString('javascript:', Sanitizer::clean('<a href="javascript:alert(1)">x</a>'));
    }

    public function test_trix_and_legacy_tags_are_mapped(): void
    {
        $this->assertSame('<h3>Заголовок</h3>', Sanitizer::clean('<h1>Заголовок</h1>'));
        $this->assertSame('<ul><li><strong>a</strong></li></ul>', Sanitizer::clean('<ul><li><b>a</b></li></ul>'));
        $this->assertSame('<p><em>a</em></p>', Sanitizer::clean('<p><i>a</i></p>'));
        $this->assertSame('<p>a</p>', Sanitizer::clean('<div>a</div>'));
    }

    public function test_allowed_markup_is_kept(): void
    {
        $html = '<h3>T</h3><p>a<br />b <strong>c</strong> <em>d</em></p><ol><li>1</li></ol><blockquote>q</blockquote>';
        $this->assertSame($html, Sanitizer::clean($html));
    }

    /** @return array<string,array{string}> */
    public static function evilLinks(): array
    {
        return [
            'entities' => ['<a href="&#106;avascript:alert(1)">x</a>'],
            'hex entities' => ['<a href="&#x6A;avascript:alert(1)">x</a>'],
            'tab inside' => ["<a href=\"java\tscript:alert(1)\">x</a>"],
            'newline inside' => ["<a href=\"java\nscript:alert(1)\">x</a>"],
            'leading space' => ['<a href="  javascript:alert(1)">x</a>'],
            'upper case' => ['<a href="JaVaScRiPt:alert(1)">x</a>'],
            'data url' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>'],
            'vbscript' => ['<a href="vbscript:msgbox(1)">x</a>'],
            'protocol relative' => ['<a href="//evil.example/x">x</a>'],
            'backslash' => ['<a href="\\evil.example/x">x</a>'],
            'encoded slashes' => ['<a href="&#47;&#47;evil.example/x">x</a>'],
            'slash backslash' => ['<a href="/\\evil.example/x">x</a>'],
            'null byte' => ["<a href=\"java\0script:alert(1)\">x</a>"],
            'colon entity' => ['<a href="javascript&colon;alert(1)">x</a>'],
            'ftp' => ['<a href="ftp://evil.example/x">x</a>'],
        ];
    }

    #[DataProvider('evilLinks')]
    public function test_evil_links_lose_their_href(string $html): void
    {
        $out = Sanitizer::clean($html);
        $this->assertDoesNotMatchRegularExpression('/href/i', $out, $out);
        $this->assertStringContainsString('x', $out);
    }

    public function test_dangerous_elements_and_attributes_are_gone(): void
    {
        $out = Sanitizer::clean('<p style="color:red" class="a" id="b" onmouseover="x()">t</p><img src="x" onerror="alert(1)"><iframe src="//e"></iframe><svg onload="a()"><script>1</script></svg><style>p{}</style><form action="/x"><input></form><object data="x"></object>');
        $this->assertSame('<p>t</p>', $out);
    }

    public function test_nested_and_broken_tags(): void
    {
        $out = Sanitizer::clean('<scr<script>ipt>alert(1)</scr</script>ipt><p><<a href="x">b</p>');
        $this->assertStringNotContainsString('<script', strtolower($out));
        $this->assertDoesNotMatchRegularExpression('/<(?!\/?(p|br|strong|em|a|ul|ol|li|h3|blockquote)\b)/i', $out);
    }

    public function test_safe_links_kept_and_external_get_rel(): void
    {
        $this->assertSame('<a href="https://vk.com/a?b=1&amp;c=2" rel="noopener noreferrer">x</a>', Sanitizer::clean('<a href="https://vk.com/a?b=1&c=2" target="_blank" onclick="x">x</a>'));
        $this->assertSame('<a href="mailto:a@b.ru">m</a>', Sanitizer::clean('<a href="mailto:a@b.ru">m</a>'));
        $this->assertSame('<a href="tel:+79991239300">t</a>', Sanitizer::clean('<a href="tel:+79991239300">t</a>'));
        $this->assertSame('<a href="/booking/">b</a>', Sanitizer::clean('<a href="/booking/">b</a>'));
    }

    public function test_quote_in_href_cannot_break_attribute(): void
    {
        $out = Sanitizer::clean('<a href="https://a.ru/&quot; onmouseover=&quot;alert(1)">x</a>');
        $this->assertDoesNotMatchRegularExpression('/<a [^>]*\sonmouseover=/', $out);
    }

    public function test_clean_is_idempotent_no_double_encoding(): void
    {
        $once = Sanitizer::clean('<p>Tom &amp; Jerry &lt;b&gt; "q" 5 &lt; 6</p><a href="https://a.ru/?a=1&amp;b=2">l</a>');
        $this->assertSame($once, Sanitizer::clean($once));
        $this->assertStringContainsString('Tom &amp; Jerry &lt;b&gt;', $once);
        $this->assertStringNotContainsString('&amp;amp;', $once);
    }

    public function test_plain_text_becomes_paragraphs(): void
    {
        $this->assertSame('<p>Первый</p><p>Второй<br />строка</p>', Sanitizer::clean("Первый\n\nВторой\nстрока"));
        $this->assertSame('<p>a &amp; b &lt; c</p>', Sanitizer::clean('a & b < c'));
        $this->assertSame('<p>a</p><p>b</p>', Sanitizer::clean("a\r\n\r\nb"));
    }

    public function test_long_input_is_not_truncated(): void
    {
        $html = '<p>' . str_repeat('Слово ', 20000) . '</p>';
        $this->assertSame(strlen($html), strlen(Sanitizer::clean($html)));
    }

    public function test_blank_detection(): void
    {
        $this->assertTrue(Sanitizer::isBlank(''));
        $this->assertTrue(Sanitizer::isBlank('<p> </p>'));
        $this->assertTrue(Sanitizer::isBlank('<br>'));
        $this->assertTrue(Sanitizer::isBlank('<p>&nbsp;</p><p><br></p>'));
        $this->assertFalse(Sanitizer::isBlank('<p>a</p>'));
        $this->assertSame('', Sanitizer::clean('<p> </p>'));
        $this->assertSame('', Sanitizer::clean(''));
        $this->assertSame('', Sanitizer::clean("  \n "));
    }
}
