<?php

namespace App\Http\Requests\TrafficRotator;

use App\Enums\StatsRange;

class DestinationChartRequest extends DestinationReportRequest
{
    /**
     * Get the range this report falls back to when the caller names none.
     *
     * A chart is a timeline, so it opens on a window short enough to still
     * show movement between buckets.
     */
    protected function defaultRange(): StatsRange
    {
        return StatsRange::LAST_30_DAYS;
    }
}
