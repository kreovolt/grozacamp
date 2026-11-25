<?php
declare(strict_types=1);

namespace Groza\Support;

final class Dates
{
    private const MONTHS = [
        1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
    ];
    private const DASH = "\u{2013}";

    /** @return array{0:int,1:int,2:int} год, месяц, день */
    private static function parts(string $ymd): array
    {
        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        return [$y, $m, $d];
    }

    public static function ru(string $ymd): string
    {
        [$y, $m, $d] = self::parts($ymd);
        return $d . ' ' . self::MONTHS[$m] . ' ' . $y;
    }

    public static function range(string $start, string $end): string
    {
        if ($start === $end) {
            return self::ru($start);
        }
        [$y1, $m1, $d1] = self::parts($start);
        [$y2, $m2, $d2] = self::parts($end);
        if ($y1 !== $y2) {
            return self::ru($start) . ' ' . self::DASH . ' ' . self::ru($end);
        }
        if ($m1 === $m2) {
            return $d1 . self::DASH . $d2 . ' ' . self::MONTHS[$m2] . ' ' . $y2;
        }
        return $d1 . ' ' . self::MONTHS[$m1] . ' ' . self::DASH . ' ' . $d2 . ' ' . self::MONTHS[$m2] . ' ' . $y2;
    }
}
