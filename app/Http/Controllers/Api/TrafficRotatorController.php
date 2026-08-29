<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficRotatorResource;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TrafficRotatorController extends Controller
{
    /**
     * List the rotators owned by the authenticated user.
     *
     * The query starts from the user's own relation, so ownership is enforced
     * by the scope rather than by filtering results afterwards.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rotators = $request->user()->rotators()
            ->withCount('destinations')
            ->orderBy('id')
            ->paginate();

        return TrafficRotatorResource::collection($rotators);
    }

    /**
     * Show a rotator together with its destinations and its lifetime traffic.
     *
     * The destinations travel with the rotator because the API exposes no
     * destination index; this is the only way to enumerate them.
     *
     * Both totals count every click on the rotator, the fallback hits with no
     * destination included, and both exclude bots — the same definition the
     * dashboard reports as views, so the two never disagree.
     */
    public function show(TrafficRotator $rotator): TrafficRotatorResource
    {
        Gate::authorize('view', $rotator);

        $rotator->load('destinations')->loadCount([
            'clicks as total_clicks' => $this->reportableClicks(...),
            // withCount counts rows, so the distinct visitor count has to
            // replace the subquery's projection rather than wrap it.
            'clicks as unique_visitors' => fn (Builder $query) => $this->reportableClicks($query)
                ->select(DB::raw('count(distinct visitor_id)')),
        ]);

        return TrafficRotatorResource::make($rotator);
    }

    /**
     * Narrow a click count to the clicks that are reported as traffic.
     *
     * @param  Builder<TrafficRotatorClick>  $query
     * @return Builder<TrafficRotatorClick>
     */
    private function reportableClicks(Builder $query): Builder
    {
        return $query->excludingBots();
    }
}
