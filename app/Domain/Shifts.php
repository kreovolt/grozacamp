<?php
declare(strict_types=1);

namespace Groza\Domain;

use DateTimeImmutable;

final class Shifts
{
    public const STATUS_LABELS = ['soon' => 'Скоро', 'open' => 'Идёт набор', 'few' => 'Мало мест', 'closed' => 'Мест нет'];

    /** Число дней включительно. */
    public static function days(string $start, string $end): int
    {
        $a = new DateTimeImmutable($start);
        $b = new DateTimeImmutable($end);
        return (int) $a->diff($b)->days + 1;
    }

    public static function costPerDay(?int $price, int $days): ?int
    {
        return $price === null || $days < 1 ? null : (int) round($price / $days);
    }

    /**
     * Не скрытые и не прошедшие смены по дате начала. «Прошла» считается днями
     * по часовому поясу $now: в последний день смена видна до полуночи.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    public static function visible(array $items, DateTimeImmutable $now): array
    {
        $today = $now->format('Y-m-d');
        $out = array_values(array_filter(
            $items,
            static fn(array $s): bool => empty($s['hidden']) && $today <= (string) ($s['dateEnd'] ?? '')
        ));
        usort($out, static fn(array $a, array $b): int => strcmp((string) $a['dateStart'], (string) $b['dateStart']));
        return $out;
    }

    /** Сезон ближайшей видимой смены. */
    public static function nearestSeason(array $items, DateTimeImmutable $now): ?string
    {
        $first = self::visible($items, $now)[0] ?? null;
        $season = $first['season'] ?? null;
        return is_string($season) && $season !== '' ? $season : null;
    }
}
