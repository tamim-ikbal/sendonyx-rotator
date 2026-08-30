<?php

namespace App\Support\Stats;

use App\Enums\StatsRange;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Support\Geo\CountryNames;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Assembles the two destination reports: the headline figures and the chart.
 *
 * The query count is the design, not an optimisation. A click on a destination
 * is by definition a click on its rotator, so every figure that compares the
 * two falls out of one range scan over (rotator_id, created_at) with a CASE
 * projection per figure; the series and the two dimension tiles take one each.
 *
 * The reports are split because they are read on different windows: stats
 * default to all time, the chart to the last 30 days. Neither pays for the
 * other's queries.
 */
final class DestinationStatsBuilder
{
    /**
     * Build the headline figures for a destination over the given range.
     *
     * @return array<string, mixed>
     */
    public function stats(TrafficRotatorDestination $destination, StatsRange $range): array
    {
        $window = $this->window($destination, $range);
        $totals = $this->totals($destination, $window);
        $activeSince = $this->activeSince($destination, $window);

        return [
            'range' => $this->rangeEnvelope($window),
            'kpis' => [
                'clicks_received' => $this->metric($totals->clicks, $totals->previousClicks, $range),
                'unique_visitors' => $this->metric($totals->visitors, $totals->previousVisitors, $range),
                'traffic_received' => $this->metric($totals->traffic, $totals->previousTraffic, $range),
                'active_since' => [
                    'date' => $activeSince->toDateString(),
                    'days' => max(0, (int) $activeSince->startOfDay()->diffInDays($window->now->startOfDay())),
                ],
            ],
        ];
    }

    /**
     * Build the chart payload for a destination over the given range.
     *
     * @return array<string, mixed>
     */
    public function chart(TrafficRotatorDestination $destination, StatsRange $range): array
    {
        $window = $this->window($destination, $range);
        $totals = $this->totals($destination, $window);
        $tops = $this->topDimensions($destination, $window);
        $days = $window->days();

        return [
            'range' => $this->rangeEnvelope($window),
            'series' => $this->series($destination, $window),
            'tiles' => [
                // The share of the pool the destination actually captured. A
                // heavily weighted destination sitting below its share is the
                // under-delivery this number exists to surface.
                'click_through_rate' => $this->derived(
                    $this->percentage($totals->clicks, $totals->traffic),
                    $this->percentage($totals->previousClicks, $totals->previousTraffic),
                    'percent',
                    $range,
                ),
                'avg_daily_clicks' => $this->derived(
                    round($totals->clicks / $days, 1),
                    round($totals->previousClicks / $days, 1),
                    'count',
                    $range,
                ),
                'top_country' => $this->countryTile($tops['country'], $totals->visitors),
                'top_device' => $this->dimensionTile($tops['device'], $totals->visitors),
            ],
        ];
    }

    /**
     * Resolve the window both reports run their queries against.
     */
    private function window(TrafficRotatorDestination $destination, StatsRange $range): StatsDateRange
    {
        /** @var CarbonImmutable $createdAt */
        $createdAt = $destination->created_at;

        return StatsDateRange::for($range, $createdAt, config()->string('app.timezone'));
    }

    /**
     * Get the moment the destination appeared, in the display timezone.
     */
    private function activeSince(TrafficRotatorDestination $destination, StatsDateRange $window): CarbonImmutable
    {
        /** @var CarbonImmutable $createdAt */
        $createdAt = $destination->created_at;

        return $createdAt->setTimezone($window->timezone);
    }

    /**
     * Describe the window both reports echo back to the caller.
     *
     * @return array{key: string, start: string, end: string, granularity: string}
     */
    private function rangeEnvelope(StatsDateRange $window): array
    {
        return [
            'key' => $window->range->value,
            'start' => $window->start->toDateString(),
            'end' => $window->end->toDateString(),
            'granularity' => $window->granularity->value,
        ];
    }

    /**
     * Read all six headline figures, both periods, in a single range scan.
     *
     * Conditional aggregation over the doubled window is what keeps this to one
     * query: the two periods are contiguous in the same index range, and both
     * come back from one consistent snapshot of the table.
     */
    private function totals(TrafficRotatorDestination $destination, StatsDateRange $window): DestinationTotals
    {
        $pivot = $window->start->utc();
        $id = $destination->id;

        $row = TrafficRotatorClick::query()
            ->excludingBots()
            ->where('rotator_id', $destination->rotator_id)
            ->whereBetween('created_at', [$window->windowStart->utc(), $window->end->utc()])
            ->selectRaw(<<<'SQL'
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as traffic_current,
                SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) as traffic_previous,
                SUM(CASE WHEN created_at >= ? AND destination_id = ? THEN 1 ELSE 0 END) as clicks_current,
                SUM(CASE WHEN created_at < ? AND destination_id = ? THEN 1 ELSE 0 END) as clicks_previous,
                COUNT(DISTINCT CASE WHEN created_at >= ? AND destination_id = ? THEN visitor_id END) as visitors_current,
                COUNT(DISTINCT CASE WHEN created_at < ? AND destination_id = ? THEN visitor_id END) as visitors_previous
                SQL, [$pivot, $pivot, $pivot, $id, $pivot, $id, $pivot, $id, $pivot, $id])
            ->toBase()
            ->first();

        if (! $row instanceof stdClass) {
            return DestinationTotals::empty();
        }

        return new DestinationTotals(
            (int) $row->traffic_current,
            (int) $row->traffic_previous,
            (int) $row->clicks_current,
            (int) $row->clicks_previous,
            (int) $row->visitors_current,
            (int) $row->visitors_previous,
        );
    }

    /**
     * Group the current period's clicks into chart buckets and zero fill them.
     *
     * @return array<int, array{bucket: string, clicks: int, unique_visitors: int}>
     */
    private function series(TrafficRotatorDestination $destination, StatsDateRange $window): array
    {
        $bucket = BucketExpression::for(
            $window->granularity,
            $window->timezone,
            DB::connection()->getDriverName(),
        );

        $rows = TrafficRotatorClick::query()
            ->excludingBots()
            ->where('destination_id', $destination->id)
            ->whereBetween('created_at', [$window->start->utc(), $window->end->utc()])
            ->selectRaw(
                $bucket->sql().' as bucket, COUNT(*) as clicks, COUNT(DISTINCT visitor_id) as unique_visitors',
                $bucket->bindings(),
            )
            // Grouped by the alias rather than by the expression again. MySQL's
            // ONLY_FULL_GROUP_BY compares parsed expressions, and two `?`
            // placeholders are two distinct parameter markers to the parser, so
            // repeating the expression fails as a nonaggregated column even
            // though the SQL is byte for byte identical.
            ->groupBy('bucket')
            ->toBase()
            ->get()
            ->keyBy('bucket');

        // Zero filling happens here rather than in SQL: neither driver has a
        // portable calendar table, and a chart that silently skipped an empty
        // bucket would compress quiet days out of the timeline.
        return array_map(function (string $key) use ($rows): array {
            $row = $rows->get($key);

            return [
                'bucket' => $key,
                'clicks' => $row instanceof stdClass ? (int) $row->clicks : 0,
                'unique_visitors' => $row instanceof stdClass ? (int) $row->unique_visitors : 0,
            ];
        }, $window->bucketKeys());
    }

    /**
     * Get the leading device and country for the current period, in one query.
     *
     * @return array{device: stdClass|null, country: stdClass|null}
     */
    private function topDimensions(TrafficRotatorDestination $destination, StatsDateRange $window): array
    {
        $grouped = $this->dimensionQuery($destination, $window, 'device')
            ->unionAll($this->dimensionQuery($destination, $window, 'country'))
            ->get()
            ->groupBy('dimension');

        return [
            'device' => $this->leading($grouped->get('device')),
            'country' => $this->leading($grouped->get('country')),
        ];
    }

    /**
     * Pick the row with the most visitors out of one dimension's rows.
     *
     * @param  Collection<int, stdClass>|null  $rows
     */
    private function leading(?Collection $rows): ?stdClass
    {
        // The label breaks ties, so two equally popular devices do not swap
        // places between two requests over identical data.
        $top = $rows?->sortBy('label')->sortByDesc('visitors')->first();

        return $top instanceof stdClass ? $top : null;
    }

    /**
     * Build one arm of the dimension union.
     */
    private function dimensionQuery(
        TrafficRotatorDestination $destination,
        StatsDateRange $window,
        string $dimension,
    ): QueryBuilder {
        // The column and the projection are chosen together so the projection
        // stays a literal string, which is what keeps it out of reach of a
        // caller supplied value.
        [$column, $select] = match ($dimension) {
            'device' => ['device_type', "'device' as dimension, device_type as label, COUNT(DISTINCT visitor_id) as visitors"],
            default => ['visitor_country', "'country' as dimension, visitor_country as label, COUNT(DISTINCT visitor_id) as visitors"],
        };

        return TrafficRotatorClick::query()
            ->excludingBots()
            ->where('destination_id', $destination->id)
            ->whereBetween('created_at', [$window->start->utc(), $window->end->utc()])
            ->whereNotNull($column)
            ->selectRaw($select)
            ->groupBy($column)
            ->toBase();
    }

    /**
     * Shape the leading country tile, named rather than coded.
     *
     * Clicks store an ISO 3166-1 alpha-2 code; the tile is read by a person, so
     * the name is resolved here at the edge of the report and the grouping
     * itself stays on the two byte column.
     *
     * @return array{name: string|null, visitor_rate: float|null}
     */
    private function countryTile(?stdClass $top, int $visitors): array
    {
        $tile = $this->dimensionTile($top, $visitors);
        $tile['name'] = CountryNames::name($tile['name']);

        return $tile;
    }

    /**
     * Shape a dimension tile, expressed as a share of the period's visitors.
     *
     * @return array{name: string|null, visitor_rate: float|null}
     */
    private function dimensionTile(?stdClass $top, int $visitors): array
    {
        if ($top === null || $visitors === 0) {
            return ['name' => null, 'visitor_rate' => null];
        }

        return [
            'name' => (string) $top->label,
            // A single dimension's distinct visitors cannot exceed the
            // destination's, but the two figures come from two queries over
            // ranges that have to stay in step. The clamp keeps a drift there
            // from surfacing as a share above 100.
            'visitor_rate' => min(100.0, round((int) $top->visitors / $visitors * 100, 1)),
        ];
    }

    /**
     * Shape a headline figure with its baseline and movement.
     *
     * @return array{value: int, previous_value: int, indicator: array{rate: float|null, position: string}}
     */
    private function metric(int $current, int $previous, StatsRange $range): array
    {
        return [
            'value' => $current,
            'previous_value' => $previous,
            'indicator' => Indicator::between($current, $previous, $range->hasBaseline())->toArray(),
        ];
    }

    /**
     * Shape a derived figure that carries a unit.
     *
     * @return array{value: float, previous_value: float, unit: string, indicator: array{rate: float|null, position: string}}
     */
    private function derived(float $current, float $previous, string $unit, StatsRange $range): array
    {
        return [
            'value' => $current,
            'previous_value' => $previous,
            'unit' => $unit,
            'indicator' => Indicator::between($current, $previous, $range->hasBaseline())->toArray(),
        ];
    }

    /**
     * Express a part of a whole as a percentage, tolerating an empty whole.
     */
    private function percentage(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 2);
    }
}
