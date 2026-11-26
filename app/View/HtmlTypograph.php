<?php
declare(strict_types=1);

namespace Groza\View;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Groza\Support\Typograph;
use SplObjectStorage;

/**
 * Типограф для готового безопасного HTML (выход Sanitizer::clean, текст страниц-документов): неразрывные пробелы
 * ставятся только в текстовых узлах, разметка и атрибуты не меняются. Слова видны через строчные теги
 * («в <strong>лагерь</strong>»), но не через границы блоков и <br>. Текст ссылки, похожий на адрес сайта или почту,
 * не трогается. Диапазоны и телефоны оборачиваются в <span class="nobr">.
 *
 * HTML собирается заново из разобранного дерева: текст и значения атрибутов экранируются как в e(), имена тегов
 * и атрибутов — только латиница; скрипты, стили, обработчики событий и элементы из DROP выбрасываются.
 *
 * Это не санитайзер: на вход — только выход Sanitizer::clean или литерал шаблона с e()-частями. Сырой HTML сюда
 * не подавать: ссылки javascript:, <plaintext>, <xmp>, <title> и т. п. этот класс не отсеивает.
 */
final class HtmlTypograph
{
    /** Строчные теги: слова внутри них и вокруг — один текст. Остальное (p, li, h3, blockquote…) и <br> — граница. */
    private const INLINE = ['a', 'strong', 'em', 'b', 'i', 'span', 'small', 'sub', 'sup', 'code', 'mark', 'abbr', 'cite', 'q', 'u', 's', 'time'];
    private const VOID = ['br', 'hr', 'img', 'wbr', 'area', 'base', 'col', 'embed', 'input', 'link', 'meta', 'source', 'track'];
    /** Не текст страницы — не выводим вовсе (на вход их не пропускает санитайзер; это вторая линия). */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'textarea', 'select', 'button', 'input', 'link', 'meta', 'base', 'img', 'frame', 'frameset', 'applet', 'audio', 'video', 'canvas'];

    /** Граница блока в общей строке текста: перевод строки типограф никогда не меняет и через него не связывает. */
    private const BREAK = "\n";

    public static function apply(string $html): string
    {
        if (trim($html) === '' || !mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $errors = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $body = $ok ? $doc->getElementsByTagName('body')->item(0) : null;
        if ($body === null) {
            return $html;
        }

        /** @var list<array{0:DOMText,1:int,2:int,3:bool}> $nodes узел, начало и длина в общей строке (в символах), не трогать ли */
        $nodes = [];
        $joined = '';
        $length = 0;
        self::collect($body, false, $nodes, $joined, $length);

        /** @var SplObjectStorage<DOMText,string> $texts новый текст узлов */
        $texts = new SplObjectStorage();
        $typed = Typograph::apply($joined);
        if (mb_strlen($typed, 'UTF-8') === $length) {
            $chars = mb_str_split($typed, 1, 'UTF-8');
            foreach ($nodes as [$node, $start, $len, $frozen]) {
                if (!$frozen) {
                    $texts[$node] = implode('', array_slice($chars, $start, $len));
                }
            }
        } else {
            // Длина разошлась (не должно случаться: пробелы уже по одному) — каждый узел отдельно, без соседей
            foreach ($nodes as [$node, , , $frozen]) {
                if (!$frozen) {
                    $texts[$node] = Typograph::apply(self::spaces($node->data));
                }
            }
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= self::html($child, $texts, false, false);
        }

        return $out;
    }

    /**
     * Обходит дерево: текст узлов (пробелы схлопнуты, как их и так показывает браузер) — в одну строку,
     * на границах блоков и у <br> — перевод строки.
     *
     * @param list<array{0:DOMText,1:int,2:int,3:bool}> $nodes
     */
    private static function collect(DOMNode $parent, bool $frozen, array &$nodes, string &$joined, int &$length): void
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = self::spaces($child->data);
                // Пробел сразу после пробела или в начале блока браузер не показывает — убираем, чтобы пробелы шли по одному
                if (str_starts_with($text, ' ') && ($joined === '' || str_ends_with($joined, ' ') || str_ends_with($joined, self::BREAK))) {
                    $text = substr($text, 1);
                }
                $len = mb_strlen($text, 'UTF-8');
                $nodes[] = [$child, $length, $len, $frozen];
                if (!$frozen) {
                    $child->data = $text;
                }
                $joined .= $text;
                $length += $len;
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $name = strtolower($child->nodeName);
            if ($name === 'br' || !in_array($name, self::INLINE, true)) {
                $joined .= self::BREAK;
                $length++;
            }
            if ($name !== 'br' && !in_array($name, self::DROP, true)) {
                self::collect($child, $frozen || ($name === 'a' && self::looksLikeAddress($child->textContent)), $nodes, $joined, $length);
            }
            if ($name !== 'br' && !in_array($name, self::INLINE, true)) {
                $joined .= self::BREAK;
                $length++;
            }
        }
    }

    /**
     * @param SplObjectStorage<DOMText,string> $texts
     * @param bool $frozen  внутри ссылки-адреса: текст как был
     * @param bool $whole   уже внутри <span class="nobr"> (повторный проход): второй span не нужен
     */
    private static function html(DOMNode $node, SplObjectStorage $texts, bool $frozen, bool $whole): string
    {
        if ($node instanceof DOMText) {
            if ($frozen || !$texts->contains($node)) {
                return e($node->data);
            }

            return $whole ? e($texts[$node]) : nobr($texts[$node]);
        }
        if (!$node instanceof DOMElement) {
            return ''; // комментарии и прочее — не текст
        }
        $name = strtolower($node->nodeName);
        if (in_array($name, self::DROP, true)) {
            return '';
        }
        $inner = '';
        $frozenInside = $frozen || ($name === 'a' && self::looksLikeAddress($node->textContent));
        $wholeInside = $whole || ($name === 'span' && $node->getAttribute('class') === 'nobr');
        foreach ($node->childNodes as $child) {
            $inner .= self::html($child, $texts, $frozenInside, $wholeInside);
        }
        if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
            return $inner; // странное имя тега — оставляем только текст
        }
        $attrs = '';
        foreach ($node->attributes ?? [] as $attr) {
            $attrName = strtolower($attr->nodeName);
            if (preg_match('/^[a-z][a-z0-9-]*$/', $attrName) !== 1 || str_starts_with($attrName, 'on') || $attrName === 'style') {
                continue;
            }
            $attrs .= ' ' . $attrName . '="' . e($attr->nodeValue) . '"';
        }
        if (in_array($name, self::VOID, true)) {
            return '<' . $name . $attrs . '>';
        }

        return '<' . $name . $attrs . '>' . $inner . '</' . $name . '>';
    }

    /** Пробелы, табуляции и переводы строк — по одному пробелу (неразрывные остаются как есть). */
    private static function spaces(string $text): string
    {
        return (string) preg_replace('/[ \t\r\n\f]+/', ' ', $text);
    }

    /** Текст ссылки — сам адрес: https://…, www.…, vk.com/…, почта. */
    private static function looksLikeAddress(string $text): bool
    {
        return preg_match('/^\s*(?:(?:https?:\/\/|ftp:\/\/|mailto:|tel:|www\.)\S+|[^\s@]+@[^\s@]+\.[^\s@]+|[a-z0-9а-яё.-]+\.[a-zа-яё]{2,}(?:[\/?#]\S*)?)\s*$/iu', $text) === 1;
    }
}
