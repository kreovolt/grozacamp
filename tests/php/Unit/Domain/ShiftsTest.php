<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Groza\Domain\Shifts;
use PHPUnit\Framework\TestCase;

final class ShiftsTest extends TestCase
{
    private function at(string $iso): DateTimeImmutable
    {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Asia/Yekaterinburg'));
    }

    public function test_days_inclusive(): void
    {
        $this->assertSame(21, Shifts::days('2026-07-02', '2026-07-22'));
        $this->assertSame(14, Shifts::days('2026-08-17', '2026-08-30'));
    }

    public function test_cost_per_day(): void
    {
        $this->assertSame(2857, Shifts::costPerDay(60000, 21));
        $this->assertNull(Shifts::costPerDay(null, 21));
        $this->assertNull(Shifts::costPerDay(60000, 0));
    }

    public function test_status_labels(): void
    {
        $this->assertSame(['soon', 'open', 'few', 'closed'], array_keys(Shifts::STATUS_LABELS));
        $this->assertSame('Идёт набор', Shifts::STATUS_LABELS['open']);
    }

    public function test_visible_excludes_hidden_and_past_sorts_by_start(): void
    {
        $items = [
            ['title' => 'B', 'dateStart' => '2027-07-25', 'dateEnd' => '2027-08-14', 'hidden' => false],
            ['title' => 'past', 'dateStart' => '2027-01-01', 'dateEnd' => '2027-01-10'],
            ['title' => 'hid', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22', 'hidden' => true],
            ['title' => 'A', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22'],
        ];
        $out = Shifts::visible($items, $this->at('2027-05-01T10:00:00+05:00'));
        $this->assertSame(['A', 'B'], array_column($out, 'title'));
        $this->assertSame([0, 1], array_keys($out));
    }

    public function test_shift_ending_today_visible_late_evening_perm(): void
    {
        $items = [['title' => 'x', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22']];
        $this->assertCount(1, Shifts::visible($items, $this->at('2027-07-22T23:30:00+05:00')));
        $this->assertCount(0, Shifts::visible($items, $this->at('2027-07-23T00:30:00+05:00')));
        // 23:30 в Перми = 18:30 UTC: день считается по часовому поясу часов
        $this->assertCount(1, Shifts::visible($items, $this->at('2027-07-22T18:30:00+00:00')));
    }

    public function test_nearest_season(): void
    {
        $items = [
            ['season' => 'Лето 2027', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22'],
            ['season' => 'Весенние каникулы 2027', 'dateStart' => '2027-03-22', 'dateEnd' => '2027-03-28'],
            ['season' => 'Старая', 'dateStart' => '2026-01-01', 'dateEnd' => '2026-01-05'],
        ];
        $this->assertSame('Весенние каникулы 2027', Shifts::nearestSeason($items, $this->at('2027-01-10T10:00:00+05:00')));
    }

    public function test_nearest_season_null_when_none(): void
    {
        $this->assertNull(Shifts::nearestSeason([], $this->at('2027-01-10T10:00:00+05:00')));
        $past = [['season' => 'S', 'dateStart' => '2020-01-01', 'dateEnd' => '2020-01-05']];
        $this->assertNull(Shifts::nearestSeason($past, $this->at('2027-01-10T10:00:00+05:00')));
    }
}
