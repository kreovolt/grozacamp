<?php
declare(strict_types=1);

namespace Groza\Support;

/**
 * Типограф для вывода на публичных страницах: обычный текст на входе, обычный текст на выходе.
 * Ставит неразрывный пробел (U+00A0) там, где по правилам русской типографики строку переносить нельзя:
 * после коротких слов и предлогов, перед частицами и тире, после сокращений и №, между инициалами и фамилией,
 * между числом и словом, в разрядах, перед ₽ и %, в адресе («Власова, 37») и внутри телефона.
 *
 * Меняются только пробелы: других символов типограф не добавляет и не заменяет (кавычки не трогает);
 * единственное исключение — дефис или короткое тире между словами («слово - слово») становится длинным тире.
 * Адреса сайтов и почты, телефоны и уже стоящие неразрывные пробелы не трогаются. Повторный проход ничего не меняет.
 * Переводы строк — граница: через них правила не действуют (их показывают как есть, например через nl2br).
 *
 * Хранимые данные типограф не меняет никогда — он работает только при выводе (Groza\View\t(), richtext()).
 */
final class Typograph
{
    private const NBSP = "\u{00A0}";

    /** Сокращения, после которых пробел неразрывный (т. д., т. е., т. п. — отдельным правилом). */
    private const ABBR = 'просп|пос|пер|пр|ул|г|д|дер|корп|обл|с|п|им|тел|стр|кв|оф';

    /** Частицы: к предыдущему слову, а не к следующему. */
    private const PARTICLES = 'ли|ль|же|ж|бы|б';

    /** Короткие предлоги из трёх букв; все слова из одной-двух букв — и так. */
    private const PREPOSITIONS = 'для|без|под|над|при|про|обо|изо|ото';

    /** Адрес сайта, почта, домен без протокола. */
    private const URL = '(?:https?:\/\/|ftp:\/\/|mailto:|tel:|www\.)[^\s<>"«»]+'
        . '|[\p{L}\p{N}._%+-]+@[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)+'
        . '|(?<![\p{L}\p{N}@.\/-])[a-z0-9а-яё-]+(?:\.[a-z0-9а-яё-]+)*\.(?:ru|com|org|net|su|рф|me|info|io|pro|site|online)(?![\p{L}\p{N}])(?:\/[^\s<>"«»]*)?';

    /** Телефон: +7 (342) 247-43-47, 8 (950) 441-12-99, +7 950 441-12-99 (пробелы — обычные или неразрывные). */
    private const PHONE = '(?<![\p{L}\p{N}])(?:\+7|8)[ \t\x{00A0}]?(?:\(\d{3,5}\)[ \t\x{00A0}]?\d{1,3}|\d{3}[ \t\x{00A0}]\d{3})(?:[-–]\d{2}){2}(?![\p{N}])';

    /** Знаки «пробельные» для правил: пробел, табуляция, неразрывный пробел и переводы строк. */
    private const WS = '[ \t\x{00A0}\r\n\f]';

    /** Неразрывный пробел вместо обычных пробелов в тексте. */
    public static function apply(string $text): string
    {
        if ($text === '' || strpbrk($text, " \t") === false || !mb_check_encoding($text, 'UTF-8')
            || preg_match('/[\x{E000}-\x{F8FF}]/u', $text) === 1) {
            return $text; // нечего менять, битая кодировка или символы, которые нужны нам как метки
        }

        // Телефон: внутри только неразрывные пробелы
        $text = (string) preg_replace_callback('/' . self::PHONE . '/u', static fn (array $m): string => strtr($m[0], [' ' => self::NBSP, "\t" => self::NBSP]), $text);

        // Адреса, почта и телефоны на время работы правил заменяются метками (один символ из личной области Unicode)
        // (в личной области 6400 знаков; адресов больше 6000 в одном тексте не бывает — лишние просто не прячем)
        $kept = [];
        $text = (string) preg_replace_callback('/' . self::URL . '|' . self::PHONE . '/iu', static function (array $m) use (&$kept): string {
            if (count($kept) >= 6000) {
                return $m[0];
            }
            $kept[] = $m[0];

            return mb_chr(0xE000 + count($kept) - 1, 'UTF-8');
        }, $text);

        $text = self::rules($text);

        return $kept === [] ? $text : self::restore($text, $kept);
    }

    /**
     * Куски текста, которые нельзя переносить внутри: диапазоны и время без пробелов («8–17», «10:00–19:00»),
     * телефоны, слова через дефис с короткой частью («из-за», «Пн–Пт», «152-ФЗ»). Браузер переносит после
     * дефиса и короткого тире — такие куски шаблон оборачивает в <span class="nobr">. Внутри адресов и почты не ищем.
     *
     * @return list<array{0:string,1:bool}> [кусок текста, держать ли его целым]; соседние куски одного вида склеены
     */
    public static function segments(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            return [[$text, false]];
        }

        // Байтовые диапазоны адресов и почты: внутри них nobr не ставим
        $skip = [];
        if (preg_match_all('/' . self::URL . '/iu', $text, $urls, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($urls[0] as [$url, $at]) {
                $skip[] = [$at, $at + strlen($url)];
            }
        }

        $token = '/' . self::PHONE
            . '|(?<![\p{L}\p{N}])\d+(?:[:.,]\d+)*(?:[-–]\d+(?:[:.,]\d+)*)+(?![\p{L}\p{N}])'
            . '|(?<![\p{L}\p{N}])[\p{L}\p{N}]+(?:[-–][\p{L}\p{N}]+)+(?![\p{L}\p{N}])/u';
        $out = [];
        $pos = 0;
        if (preg_match_all($token, $text, $found, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($found[0] as [$piece, $at]) {
                $end = $at + strlen($piece);
                if (!self::keepWhole($piece)) {
                    continue;
                }
                foreach ($skip as [$from, $to]) {
                    if ($at < $to && $end > $from) {
                        continue 2;
                    }
                }
                self::push($out, substr($text, $pos, $at - $pos), false);
                self::push($out, $piece, true);
                $pos = $end;
            }
        }
        self::push($out, substr($text, $pos), false);

        return $out;
    }

    /** Цифры через тире или дефис, телефон — всегда; слово через дефис — если одна из частей не длиннее трёх знаков. */
    private static function keepWhole(string $piece): bool
    {
        if (preg_match('/^[\d+(]/', $piece) === 1 && preg_match('/^[\d:.,\-–+() \x{00A0}]+$/u', $piece) === 1) {
            return true;
        }
        $parts = preg_split('/[-–]/u', $piece) ?: [];
        if (mb_strlen($piece) > 24) {
            return false;
        }
        foreach ($parts as $part) {
            if (mb_strlen($part) <= 3) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{0:string,1:bool}> $out */
    private static function push(array &$out, string $piece, bool $whole): void
    {
        if ($piece === '') {
            return;
        }
        $last = count($out) - 1;
        if ($last >= 0 && $out[$last][1] === $whole && !$whole) {
            $out[$last][0] .= $piece;

            return;
        }
        $out[] = [$piece, $whole];
    }

    /** @param list<string> $kept */
    private static function restore(string $text, array $kept): string
    {
        $map = [];
        foreach ($kept as $i => $original) {
            $map[mb_chr(0xE000 + $i, 'UTF-8')] = $original;
        }

        return strtr($text, $map);
    }

    /** Правила по порядку. Каждое меняет только пробелы (и тире между словами), поэтому повторный проход ничего не меняет. */
    private static function rules(string $text): string
    {
        $nb = self::NBSP;
        $ws = self::WS;
        $notWs = '[^ \t\x{00A0}\r\n\f]';

        // Дефис или короткое тире с пробелами: между словами — длинное тире с неразрывным пробелом перед ним;
        // рядом с числом (диапазон «29 июня – 12 июля», «2026 – 2027») тире остаётся как есть, неразрывный пробел — тоже перед ним
        $text = (string) preg_replace_callback('/(' . $notWs . ')[ \t]+([-–])[ \t]+(?=(' . $notWs . '))/u', static function (array $m) use ($nb): string {
            $number = preg_match('/^\p{N}$/u', $m[1]) === 1 || preg_match('/^\p{N}$/u', $m[3]) === 1;

            return $m[1] . $nb . ($number ? $m[2] : '—') . ' ';
        }, $text);
        // Длинное тире — не в начало строки
        $text = (string) preg_replace('/(?<=' . $notWs . ')[ \t]+—/u', $nb . '—', $text);

        // т. д., т. е., т. п.: внутри неразрывный пробел (после — обычный: часто это конец предложения)
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])(т\.)[ \t]+(?=[дпе]\.)/iu', '$1' . $nb, $text);
        // Сокращения: г. Пермь, ул. Ленина, д. 5, им. Вагина, тел. …
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])(?<!т\.\x{00A0})(?<!т\. )((?:' . self::ABBR . ')\.)[ \t]+(?=[^\s\-–—])/iu', '$1' . $nb, $text);
        // № 201, § 3
        $text = (string) preg_replace('/([№§])[ \t]+(?=\S)/u', '$1' . $nb, $text);

        // Инициалы: «Е. А. Лучников», «Е.А. Лучников», «Лучников Е. А.»
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])(\p{Lu}\.)[ \t]+(?=\p{Lu}(?:\.|\p{Ll}))/u', '$1' . $nb, $text);
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])(\p{Lu}\p{Ll}+)[ \t]+(?=\p{Lu}\.)/u', '$1' . $nb, $text);

        // Разряды: 40 000, 1 250 000
        $text = (string) preg_replace_callback('/(?<![\p{N}.,])\d{1,3}(?:[ \t\x{00A0}]\d{3})+(?![\p{N}])/u', static fn (array $m): string => strtr($m[0], [' ' => $nb, "\t" => $nb]), $text);
        // 60 000 ₽, 10 %
        $text = (string) preg_replace('/(?<=\p{N})[ \t]+(?=[₽%‰])/u', $nb, $text);
        // Число и следующее слово: 14 дней, 8–17 лет, в 10:00 сбор, 2027 г.
        // (год после месяца — «12 июля 2027» — не привязываем: в узкой колонке «2–22 июля 2027» целиком не помещается)
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])(\p{N}+(?:[.,:\/–-]\p{N}+)*)[ \t]+(?=\p{L})/u', '$1' . $nb, $text);
        // Номер дома после запятой: Власова, 37 / 37а / 37/2. После номера дома слова нет («Власова, 37.», «37 (пример)»);
        // «в отряде, 2 вожатых» — это счёт, а не адрес: там число держится со своим словом (правило выше), а не с запятой
        $text = (string) preg_replace('/(?<=\p{L}),[ \t]+(?=\p{N}{1,4}(?:\p{L}{1,2})?(?:\/\p{N}{1,4})?(?![\p{L}\p{N}])(?![ \t\x{00A0}]*[\p{L}\p{N}]))/u', ',' . $nb, $text);

        // Частицы — к предыдущему слову: пришёл бы, тот же, знаете ли
        $text = (string) preg_replace('/(?<=[\p{L}\p{N}])[ \t]+(?=(?:' . self::PARTICLES . ')(?![\p{L}\p{N}]))/iu', $nb, $text);
        // Короткие слова и предлоги — к следующему: в лесу, и в лагере, для детей. Частицы вперёд не цепляем,
        // аббревиатуры из двух заглавных («ФЗ», «ВК», «РФ») — тоже: это не предлоги (заглавными сайт пишет через CSS, не в тексте)
        $text = (string) preg_replace(
            '/(?<![\p{L}\p{N}])(?!(?:' . self::PARTICLES . ')' . $ws . ')(?!(?-i:[А-ЯЁ]{2})[ \t])((?:' . self::PREPOSITIONS . ')|[а-яё]{1,2})[ \t]+(?=[^\s\-–—])/iu',
            '$1' . $nb,
            $text
        );

        return $text;
    }
}
