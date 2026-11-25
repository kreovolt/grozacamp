<?php
declare(strict_types=1);

namespace Groza\Forms;

/** Очистка значений полей из POST. Всё, что может попасть в заголовок письма, проходит через line(). */
final class Fields
{
    /** Одна строка: любые управляющие символы и переводы строк → пробел, пробелы схлопнуты. Не строка → ''. */
    public static function line(mixed $v): string
    {
        if (!is_string($v)) {
            return '';
        }
        $v = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}\s]+/u', ' ', $v) ?? '';

        return trim($v);
    }

    /** Многострочный текст: переводы строк сохраняются (\n), прочие управляющие символы удаляются. */
    public static function text(mixed $v): string
    {
        if (!is_string($v)) {
            return '';
        }
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = preg_replace('/[^\P{Cc}\n]/u', '', $v) ?? '';
        $v = preg_replace('/[ \t]+\n/', "\n", $v) ?? '';
        $v = preg_replace('/\n{3,}/', "\n\n", $v) ?? '';

        return trim($v);
    }

    /** Целое число из строки из одних цифр, иначе null. */
    public static function int(mixed $v): ?int
    {
        $s = self::line($v);

        return preg_match('/^\d{1,3}$/', $s) === 1 ? (int) $s : null;
    }
}
