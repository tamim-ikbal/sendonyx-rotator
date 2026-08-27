<?php

namespace App\Enums;

enum StatsRange: string
{
    case TODAY = 'today';
    case LAST_7_DAYS = 'last_7_days';
    case LAST_30_DAYS = 'last_30_days';
    case LAST_6_MONTHS = 'last_6_months';
    case THIS_YEAR = 'this_year';
    case ALL_TIME = 'all_time';

    /**
     * Resolve the bucket size that keeps this range readable on a chart.
     */
    public function granularity(): Granularity
    {
        return match ($this) {
            self::TODAY => Granularity::HOUR,
            self::LAST_7_DAYS, self::LAST_30_DAYS => Granularity::DAY,
            self::LAST_6_MONTHS => Granularity::WEEK,
            self::THIS_YEAR, self::ALL_TIME => Granularity::MONTH,
        };
    }

    /**
     * Determine whether this range can be compared against a preceding period.
     *
     * All time has no baseline: doubling an unbounded window would scan history
     * twice to produce a comparison that is discarded.
     */
    public function hasBaseline(): bool
    {
        return $this !== self::ALL_TIME;
    }
}
