<?php
declare(strict_types=1);

namespace Groza\Support;

use DateTimeImmutable;

interface Clock
{
    /** Текущий момент во временной зоне лагеря (Asia/Yekaterinburg). */
    public function now(): DateTimeImmutable;
}
