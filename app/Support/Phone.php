<?php
declare(strict_types=1);

namespace Groza\Support;

final class Phone
{
    /** Российский номер в любом написании → +7XXXXXXXXXX; иначе null. */
    public static function normalize(string $raw): ?string
    {
        $clean = preg_replace('/[\s()\-\x{00A0}]+/u', '', $raw) ?? '';
        if (!preg_match('/^\+?(\d+)$/', $clean, $m)) {
            return null;
        }
        $d = $m[1];
        if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) {
            $d = substr($d, 1);
        }
        return strlen($d) === 10 ? '+7' . $d : null;
    }

    public static function pretty(string $e164): string
    {
        if (!preg_match('/^\+7(\d{3})(\d{3})(\d{2})(\d{2})$/', $e164, $m)) {
            return $e164;
        }
        return "+7 ($m[1]) $m[2]-$m[3]-$m[4]";
    }
}
