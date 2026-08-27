<?php

namespace App\Http\Requests\TrafficRotator;

use App\Enums\StatsRange;

class DestinationStatsRequest extends DestinationReportRequest
{
    /**
     * Get the range this report falls back to when the caller names none.
     *
     * Headline figures read as lifetime totals unless a caller narrows them,
     * so these open on the whole history instead of the chart's 30 days.
     */
    protected function defaultRange(): StatsRange
    {
        return StatsRange::ALL_TIME;
    }
}
