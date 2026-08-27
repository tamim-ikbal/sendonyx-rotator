<?php

namespace App\Support\Stats;

use App\Enums\Granularity;
use App\Enums\StatsRange;
use Carbon\CarbonImmutable;

/**
 * The window a stats request covers, resolved once and reused by every query.
 *
 * Every boundary here lives in the display timezone. Clicks are stored in UTC,
 * so callers convert on the way into a binding; keeping the window local is
 * what lets the bucket keys generated in PHP match the ones SQL produces.
 */
final readonly class StatsDateRange
{
    private function __construct(
        public StatsRange $range,
        public Granularity $granularity,
        public string $timezone,
        public CarbonImmutable $now,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $previousStart,
        public CarbonImmutable $windowStart,
    ) {}

    /**
     * Resolve the window for a range, anchored to when the destination appeared.
     */
    public static function for(
        StatsRange $range,
        CarbonImmutable $destinationCreatedAt,
        string $timezone,
        ?CarbonImmutable $now = null,
    ): self {
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);
        $createdAt = $destinationCreatedAt->setTimezone($timezone);

        $end = $now->endOfDay();

        $start = match ($range) {
            StatsRange::TODAY => $now->startOfDay(),
            StatsRange::LAST_7_DAYS => $now->startOfDay()->subDays(6),
            StatsRange::LAST_30_DAYS => $now->startOfDay()->subDays(29),
            StatsRange::LAST_6_MONTHS => $now->startOfDay()->subMonths(6)->addDay(),
            StatsRange::THIS_YEAR => $now->startOfYear(),
            StatsRange::ALL_TIME => $createdAt,
        };

        // All time has nothing to compare against, so the baseline collapses
        // onto the start: the previous window is empty rather than unbounded,
        // and the query below never widens to scan history twice.
        $previousStart = $range->hasBaseline()
            ? $start->subSeconds($end->getTimestamp() - $start->getTimestamp() + 1)
            : $start;

        return new self(
            $range,
            $range->granularity(),
            $timezone,
            $now,
            $start,
            $end,
            $previousStart,
            // A destination cannot have competed for traffic that arrived
            // before it existed, so the scan starts at whichever came later.
            // GREATEST() would push this into SQL for no benefit.
            $previousStart->max($createdAt),
        );
    }

    /**
     * Get the number of days the current period spans.
     */
    public function days(): int
    {
        return max(1, (int) $this->start->startOfDay()->diffInDays($this->end) + 1);
    }

    /**
     * Get every bucket key the chart must show, in order.
     *
     * This is the zero-fill template. A bucket with no clicks is absent from
     * the grouped result, and a chart that silently skips it would compress
     * quiet days out of the timeline.
     *
     * @return array<int, string>
     */
    public function bucketKeys(): array
    {
        $keys = [];
        $cursor = $this->granularity->startOf($this->start);

        while ($cursor <= $this->end) {
            $keys[] = $this->granularity->keyFor($cursor);
            $cursor = $this->granularity->advance($cursor);
        }

        return $keys;
    }
}
