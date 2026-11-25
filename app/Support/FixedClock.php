<?php
declare(strict_types=1);

namespace Groza\Support;

use DateTimeImmutable;
use DateTimeZone;

final class FixedClock implements Clock
{
    private DateTimeImmutable $moment;

    public function __construct(string $iso, string $timezone = 'Asia/Yekaterinburg')
    {
        $this->moment = (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone($timezone));
    }

    public function now(): DateTimeImmutable
    {
        return $this->moment;
    }
}
