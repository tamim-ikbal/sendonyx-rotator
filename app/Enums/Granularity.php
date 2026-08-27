<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum Granularity: string
{
    case HOUR = 'hour';
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';

    /**
     * Build the bucket key for the given moment.
     *
     * These keys must match the SQL bucket expressions byte for byte. Weeks are
     * keyed by the date of their Monday rather than a week number, because
     * MySQL and SQLite disagree on week numbering.
     */
    public function keyFor(CarbonImmutable $moment): string
    {
        return match ($this) {
            self::HOUR => $moment->format('Y-m-d H:00'),
            self::DAY => $moment->format('Y-m-d'),
            self::WEEK => $moment->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d'),
            self::MONTH => $moment->format('Y-m'),
        };
    }

    /**
     * Snap the given moment back to the start of its bucket.
     */
    public function startOf(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::HOUR => $moment->startOfHour(),
            self::DAY => $moment->startOfDay(),
            self::WEEK => $moment->startOfWeek(CarbonInterface::MONDAY),
            self::MONTH => $moment->startOfMonth(),
        };
    }

    /**
     * Step forward to the start of the next bucket.
     */
    public function advance(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::HOUR => $moment->addHour(),
            self::DAY => $moment->addDay(),
            self::WEEK => $moment->addWeek(),
            self::MONTH => $moment->addMonth(),
        };
    }
}
