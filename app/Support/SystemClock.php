<?php
declare(strict_types=1);

namespace Groza\Support;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    private DateTimeZone $tz;

    public function __construct(string $timezone = 'Asia/Yekaterinburg')
    {
        $this->tz = new DateTimeZone($timezone);
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->tz);
    }
}
