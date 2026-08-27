<?php

namespace App\Support\Stats;

/**
 * The six headline figures a chart request needs, for both periods.
 *
 * They travel together because they are read together: one conditional
 * aggregation over the doubled window produces all twelve numbers at once.
 */
final readonly class DestinationTotals
{
    public function __construct(
        public int $traffic,
        public int $previousTraffic,
        public int $clicks,
        public int $previousClicks,
        public int $visitors,
        public int $previousVisitors,
    ) {}

    /**
     * Get the totals for a destination with no clicks in the window at all.
     */
    public static function empty(): self
    {
        return new self(0, 0, 0, 0, 0, 0);
    }
}
