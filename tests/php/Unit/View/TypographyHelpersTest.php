<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\View;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

use function Groza\View\e;
use function Groza\View\pending;
use function Groza\View\richtext;
use function Groza\View\t;
use function Groza\View\typeset;

/** t() — типограф + экранирование для текста страницы; richtext() и typeset() — типограф в текстовых узлах готового HTML. */
final class TypographyHelpersTest extends TestCase
{
    private const N = "\u{00A0}";

    public function test_t_escapes_like_e(): void
    {
        $this->assertSame('Tom &amp; Jerry &lt;script&gt;alert(1)&lt;/script&gt;', t('Tom & Jerry <script>alert(1)</script>'));
        $this->assertSame('в' . self::N . '&quot;лесу&quot; &#039;x&#039; &lt;i&gt;', t('в "лесу" \'x\' <i>'));
        $this->assertSame('', t(null));
        $this->assertSame('', t(''));
    }

    public function test_t_puts_no_break_spaces_in_the_footer_address(): void
    {
        $n = self::N;
        $this->assertSame("г.{$n}Пермь, ул.{$n}Милиционера Власова,{$n}37", t('г. Пермь, ул. Милиционера Власова, 37'));
    }

    public function test_t_differs_from_e_only_in_spaces(): void
    {
        foreach (['Лагерь в лесу & поле', 'Сбор у школы № 136 им. Я. А. Вагина', '<b>для</b> "детей"', 'С отрядом работают двое вожатых'] as $s) {
            $this->assertSame(e($s), str_replace(self::N, ' ', t($s)), $s);
        }
    }

    public function test_t_wraps_ranges_and_phones_in_nobr(): void
    {
        $n = self::N;
        $this->assertSame('<span class="nobr">10:00–19:00</span>', t('10:00–19:00'));
        $this->assertSame("<span class=\"nobr\">Пн–Пт</span>{$n}<span class=\"nobr\">10:00–19:00</span>", t('Пн–Пт 10:00–19:00'));
        $this->assertSame("<span class=\"nobr\">8–17</span>{$n}лет", t('8–17 лет'));
        $this->assertSame("<span class=\"nobr\">+7{$n}(342){$n}247-43-47</span>", t('+7 (342) 247-43-47'));
        $this->assertSame("<span class=\"nobr\">из-за</span>{$n}дождя", t('из-за дождя'));
        $this->assertSame('https://site.ru/10-20', t('https://site.ru/10-20'), 'внутри адреса nobr нет');
    }

    public function test_t_is_stable_on_already_typographed_text(): void
    {
        foreach (['Пн–Пт 10:00–19:00', 'г. Пермь, ул. Милиционера Власова, 37', 'до 24 детей'] as $s) {
            $this->assertSame(t($s), t(\Groza\Support\Typograph::apply($s)), $s);
        }
    }

    public function test_pending_typographs_a_filled_value(): void
    {
        $this->assertSame('в' . self::N . 'лесу &amp; поле', pending('в лесу & поле'));
        $this->assertStringContainsString('Информация обновляется', pending(''));
    }

    public function test_richtext_typographs_text_nodes(): void
    {
        $n = self::N;
        $this->assertSame("<p>Лагерь в{$n}лесу</p>", richtext('<p>Лагерь в лесу</p>'));
        $this->assertSame("<p>Лагерь в{$n}лесу</p><p>и{$n}в{$n}поле</p>", richtext("Лагерь в лесу\n\nи в поле"), 'текст без тегов');
        $this->assertSame("<p>строка в<br>лесу</p>", richtext('<p>строка в<br>лесу</p>'), 'через <br> не склеиваем');
        $this->assertSame('<p>Едем в</p><p>лагерь</p>', richtext('<p>Едем в</p><p>лагерь</p>'), 'через границу абзаца не склеиваем');
    }

    public function test_richtext_sees_words_across_inline_tags(): void
    {
        $n = self::N;
        $this->assertSame("<p>Едем в{$n}<strong>лагерь</strong></p>", richtext('<p>Едем в <strong>лагерь</strong></p>'));
        $this->assertSame("<p><strong>в</strong>{$n}лагерь</p>", richtext('<p><strong>в</strong> лагерь</p>'));
        $this->assertSame("<p><em>в</em>{$n}<strong>лесу</strong></p>", richtext('<p><em>в</em> <strong>лесу</strong></p>'));
    }

    public function test_richtext_wraps_ranges_in_nobr(): void
    {
        $n = self::N;
        $this->assertSame(
            "<p>Офис работает <span class=\"nobr\">Пн–Пт</span>{$n}<span class=\"nobr\">10:00–19:00</span></p>",
            richtext('<p>Офис работает Пн–Пт 10:00–19:00</p>')
        );
    }

    public function test_richtext_leaves_urls_and_hrefs_alone(): void
    {
        $out = richtext('<p>Группа: <a href="https://vk.com/club-10-20?x=1&amp;y=2">vk.com/club-10-20</a>, почта <a href="mailto:office@groza.ru">office@groza.ru</a></p>');
        $this->assertStringContainsString('<a href="https://vk.com/club-10-20?x=1&amp;y=2" rel="noopener noreferrer">vk.com/club-10-20</a>', $out);
        $this->assertStringContainsString('<a href="mailto:office@groza.ru">office@groza.ru</a>', $out);
        $this->assertStringNotContainsString('nobr', $out);
    }

    public function test_richtext_is_idempotent(): void
    {
        foreach (['<p>Едем в <strong>лагерь</strong> на 14 дней</p>', '<ul><li>в лесу</li><li>и в поле</li></ul>', '<h3>Посещение в лагере</h3><p>Пн–Пт 10:00–19:00</p>'] as $html) {
            $once = richtext($html);
            $this->assertSame($once, typeset($once), $html);
            $this->assertSame($once, richtext($once), $html);
        }
    }

    public function test_typeset_keeps_heading_ids_and_bare_text(): void
    {
        $n = self::N;
        $this->assertSame("<h3 id=\"poseshchenie\">Посещение в{$n}лагере</h3>", typeset('<h3 id="poseshchenie">Посещение в лагере</h3>'));
        $this->assertSame("Едем в{$n}лес", typeset('Едем в лес'), 'текст без обёртки не превращается в абзац');
        $this->assertSame('', typeset(''));
    }

    /** Типограф работает после санитайзера и разбирает HTML заново — результат всё так же безопасно выводить без экранирования. */
    public function test_richtext_output_is_still_safe(): void
    {
        $evil = '<p>в &lt;script&gt;alert(1)&lt;/script&gt; лесу</p><script>alert(2)</script><img src=x onerror=alert(3)>'
            . '<a href="javascript:alert(4)" onclick="alert(5)">в лес</a><p title="x" style="color:red" onmouseover="alert(6)">в поле</p>'
            . '<a href="https://a.ru/?q=&quot;&gt;&lt;script&gt;alert(7)&lt;/script&gt;">в лес</a>'
            . '<p>&amp;lt;b&amp;gt; в тексте</p><p>"><svg onload=alert(8)></svg></p>';
        $out = richtext($evil);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $out, 'экранированный текст остаётся текстом');
        $this->assertStringContainsString('&amp;lt;b&amp;gt;', $out, 'двойное экранирование не раскрывается');
        foreach (['<script', '<img', '<svg', 'onerror', 'onclick', 'onmouseover', 'onload', 'javascript:', 'style=', 'title='] as $bad) {
            $this->assertStringNotContainsString($bad, $out, $bad);
        }

        // Разбираем результат: только разрешённые теги и атрибуты, у span — только class="nobr"
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $out . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $allowed = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'h3', 'blockquote', 'span'];
        $body = $doc->getElementsByTagName('body')->item(0);
        $this->assertNotNull($body);
        foreach ($body->getElementsByTagName('*') as $el) {
            $this->assertInstanceOf(DOMElement::class, $el);
            $this->assertContains($el->nodeName, $allowed, $el->nodeName);
            foreach ($el->attributes ?? [] as $attr) {
                $this->assertContains($attr->nodeName, ['href', 'rel', 'class', 'id'], $el->nodeName . '[' . $attr->nodeName . ']');
                if ($attr->nodeName === 'href') {
                    $this->assertDoesNotMatchRegularExpression('/^\s*javascript:/i', $attr->nodeValue ?? '');
                }
            }
            if ($el->nodeName === 'span') {
                $this->assertSame('nobr', $el->getAttribute('class'));
            }
        }
    }

    public function test_typeset_drops_scripts_and_event_handlers_even_if_given_raw_html(): void
    {
        $out = typeset('<p onclick="x()">в лесу</p><script>alert(1)</script><style>p{}</style>');
        $this->assertSame('<p>в' . self::N . 'лесу</p>', $out);
    }
}
