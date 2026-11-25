<?php
declare(strict_types=1);

namespace Groza\Admin;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Очистка текста с оформлением (richtext). Это граница безопасности: всё, что попадает на публичные страницы
 * из админки или из JSON, проходит через clean() — и при сохранении, и при выводе.
 *
 * Разрешено: p, br, strong, em, a[href], ul, ol, li, h3, blockquote. Остальные теги снимаются (текст остаётся),
 * script/style/iframe и подобные удаляются вместе с содержимым. Атрибуты — только href у ссылок (http, https, mailto, tel
 * и внутренние «/путь»; «//хост» и обратные слеши не пропускаются). Внешним ссылкам добавляется rel="noopener noreferrer".
 * Теги из Trix и из старых текстов приводятся к разрешённым: b→strong, i→em, h1–h6→h3, div→p.
 * Текст без тегов (из прежнего обычного поля) превращается в абзацы: пустая строка — новый <p>, перевод строки — <br>.
 */
final class Sanitizer
{
    /** Не обрезать длинные тексты (по умолчанию Symfony режет вход на 20 000 знаков). */
    private const MAX_INPUT = 4000000;

    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(string $html): string
    {
        $html = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $html);
        if (trim($html) === '') {
            return '';
        }
        if (preg_match('/<[a-zA-Z!\/?]/', $html) !== 1) {
            $html = self::paragraphs($html);
        }
        $out = self::links(self::sanitizer()->sanitize(self::rename($html)));

        return self::isBlank($out) ? '' : $out;
    }

    /** Пусто ли оформленное значение: после снятия тегов и пробелов (в том числе неразрывных) ничего нет. */
    public static function isBlank(string $html): bool
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($plain, " \t\n\r\0\x0B\u{00A0}\u{200B}") === '';
    }

    /** Теги, которые санитайзер Symfony 6.4 не переименовывает сам: b→strong, i→em, h1–h6→h3, div→p (дальше идёт проверка по списку). */
    private static function rename(string $html): string
    {
        $map = ['b' => 'strong', 'i' => 'em', 'div' => 'p', 'h1' => 'h3', 'h2' => 'h3', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3'];

        return preg_replace_callback(
            '#<(/?)(b|i|div|h[1-6])(?=[\s/>])#i',
            static fn (array $m): string => '<' . $m[1] . ($map[strtolower($m[2])] ?? strtolower($m[2])),
            $html
        ) ?? $html;
    }

    /**
     * Выход санитайзера канонический (<a href="…">), поэтому ссылки правим по нему:
     * «//хост», обратные слеши и управляющие знаки в адресе — ссылка снимается (текст остаётся);
     * внешним (http/https) добавляется rel="noopener noreferrer".
     */
    private static function links(string $html): string
    {
        return preg_replace_callback('#<a href="([^"]*)">(.*?)</a>#s', static function (array $m): string {
            $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#^\s*[/\\\\]{2}#', $href) === 1 || str_contains($href, '\\') || preg_match('/[\x00-\x20\x7F]/', $href) === 1) {
                return $m[2];
            }
            $rel = preg_match('#^https?:#i', $href) === 1 ? ' rel="noopener noreferrer"' : '';
            // Symfony записывает «@», «+» и «=» числовыми кодами; в значении атрибута в кавычках они безопасны и так читаются легче
            $shown = str_replace(['&#64;', '&#43;', '&#61;'], ['@', '+', '='], $m[1]);

            return '<a href="' . $shown . '"' . $rel . '>' . $m[2] . '</a>';
        }, $html) ?? $html;
    }

    private static function paragraphs(string $text): string
    {
        $out = [];
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $para) {
            $para = trim($para);
            if ($para !== '') {
                $out[] = '<p>' . str_replace("\n", '<br>', htmlspecialchars($para, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
            }
        }

        return implode('', $out);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }
        $config = (new HtmlSanitizerConfig())
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('a', ['href'])
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('h3')
            ->allowElement('blockquote')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(true)
            ->withMaxInputLength(self::MAX_INPUT);
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'textarea', 'select', 'button', 'head', 'title', 'applet', 'audio', 'video', 'canvas', 'noembed', 'noframes', 'xmp', 'frameset', 'frame'] as $tag) {
            $config = $config->dropElement($tag);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
