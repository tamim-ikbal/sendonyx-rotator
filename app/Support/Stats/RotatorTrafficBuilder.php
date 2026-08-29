<?php

namespace App\Support\Stats;

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use stdClass;

/**
 * Splits a rotator's traffic across the identifiers its clicks were stamped with.
 *
 * `plan_uid` and `customer_uid` are copied onto the click row when it is
 * recorded, so these figures answer "what did this plan earn", not "what does
 * it own today": moving a destination onto another plan leaves its past
 * traffic where it was earned. The columns on the destination are the current
 * attribution and only seed new clicks; nothing here joins back to them.
 *
 * That stamping is also what keeps this to one index-only scan per breakdown.
 * The index each one rides — (rotator_id, uid, device_type) — carries the bot
 * filter too, so no grouped row is ever fetched from the table.
 */
final class RotatorTrafficBuilder
{
    /**
     * Total the rotator's clicks per plan.
     *
     * @return array<int, array{plan_uid: string, clicks: int}>
     */
    public function byPlan(TrafficRotator $rotator): array
    {
        return array_map(
            static fn (stdClass $row): array => ['plan_uid' => (string) $row->uid, 'clicks' => (int) $row->clicks],
            $this->groupedByStampedUid($rotator, 'plan_uid'),
        );
    }

    /**
     * Total the rotator's clicks per customer.
     *
     * @return array<int, array{customer_uid: string, clicks: int}>
     */
    public function byCustomer(TrafficRotator $rotator): array
    {
        return array_map(
            static fn (stdClass $row): array => ['customer_uid' => (string) $row->uid, 'clicks' => (int) $row->clicks],
            $this->groupedByStampedUid($rotator, 'customer_uid'),
        );
    }

    /**
     * Group a rotator's clicks by one of the identifiers stamped on them.
     *
     * Rows without a value are dropped rather than collected into a null
     * bucket. Two things land there — clicks on destinations nobody had
     * attributed at the time, and the fallback hits that had no destination at
     * all — and merging them into one row would report a total that means
     * neither.
     *
     * @return array<int, stdClass>
     */
    private function groupedByStampedUid(TrafficRotator $rotator, string $column): array
    {
        // Matched against a fixed set rather than interpolated, so the column
        // can never be reached by a caller supplied value.
        $uid = match ($column) {
            'plan_uid' => 'plan_uid',
            default => 'customer_uid',
        };

        /** @var array<int, stdClass> $rows */
        $rows = TrafficRotatorClick::query()
            ->excludingBots()
            ->where('rotator_id', $rotator->id)
            ->whereNotNull($uid)
            ->selectRaw($uid.' as uid, COUNT(*) as clicks')
            ->groupBy($uid)
            // The identifier breaks ties, so two equally busy plans do not
            // swap places between two requests over identical data.
            ->orderByDesc('clicks')
            ->orderBy('uid')
            ->toBase()
            ->get()
            ->all();

        return $rows;
    }
}
