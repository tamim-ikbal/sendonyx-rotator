<?php

namespace App\Http\Controllers\Rotator;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrafficRotator\StoreRotatorRequest;
use App\Http\Requests\TrafficRotator\UpdateRotatorRequest;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard a user manages their own rotators from.
 *
 * It is the browser facing counterpart to the Sanctum API and shares its write
 * requests, so the two paths can never drift on what a valid rotator looks
 * like. Destinations stay read only here: this screen reports on them, and the
 * API owns creating and editing them.
 */
final class RotatorController extends Controller
{
    /**
     * List the rotators owned by the authenticated user.
     *
     * The query starts from the user's own relation, so ownership is a scope
     * rather than a filter applied to results that were already fetched.
     */
    public function index(Request $request): Response
    {
        $rotators = $request->user()->rotators()
            ->withCount(['destinations', 'clicks' => $this->reportableClicks(...)])
            ->latest('id')
            ->get();

        return Inertia::render('rotators/index', [
            'rotators' => $rotators->map(fn (TrafficRotator $rotator): array => [
                ...$this->rotatorPayload($rotator),
                'destinations_count' => (int) $rotator->destinations_count,
                'views_count' => (int) $rotator->clicks_count,
            ]),
            // The page hides its create action on this, so the limit the store
            // request enforces is never offered as something the user can do.
            'canCreateRotator' => $request->user()->can('create', TrafficRotator::class),
        ]);
    }

    /**
     * Show the form for creating a rotator.
     *
     * Authorised for the same reason `edit` is: the form only exists to submit
     * a write, so a caller the store request would refuse never reaches it.
     */
    public function create(): Response
    {
        Gate::authorize('create', TrafficRotator::class);

        return Inertia::render('rotators/create');
    }

    /**
     * Store a newly created rotator.
     */
    public function store(StoreRotatorRequest $request): RedirectResponse
    {
        $rotator = new TrafficRotator($request->validated());

        // user_id is deliberately absent from the model's fillable set: it is
        // the ownership boundary the whole policy rests on, and a request must
        // never be able to name it.
        $rotator->user_id = $request->user()->id;

        $rotator->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rotator created.')]);

        return to_route('rotator.show', $rotator);
    }

    /**
     * Show a rotator's traffic: its total views and how they split across destinations.
     */
    public function show(TrafficRotator $rotator): Response
    {
        Gate::authorize('view', $rotator);

        $destinations = $rotator->destinations()
            ->withCount(['clicks' => $this->reportableClicks(...)])
            ->orderBy('id')
            ->get();

        return Inertia::render('rotators/show', [
            'rotator' => $this->rotatorPayload($rotator),
            // Every click on the rotator, destinations and default url fallback
            // alike. The page derives the fallback share from the difference,
            // so the destination rows never have to add up to this on their own.
            'totalViews' => $rotator->clicks()->excludingBots()->count(),
            'destinations' => $destinations->map(fn (TrafficRotatorDestination $destination): array => [
                'uuid' => $destination->uuid,
                'url' => $destination->url,
                'weight' => $destination->weight,
                'status' => $destination->status->value,
                'views_count' => (int) $destination->clicks_count,
                'created_at' => $destination->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Show the form for editing a rotator.
     *
     * Authorised as `update` rather than `view`: the form exists only to submit
     * a write, so a caller who could not perform it should never reach it.
     */
    public function edit(TrafficRotator $rotator): Response
    {
        Gate::authorize('update', $rotator);

        return Inertia::render('rotators/edit', [
            'rotator' => $this->rotatorPayload($rotator),
        ]);
    }

    /**
     * Update a rotator.
     */
    public function update(UpdateRotatorRequest $request, TrafficRotator $rotator): RedirectResponse
    {
        $rotator->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rotator updated.')]);

        return to_route('rotator.show', $rotator);
    }

    /**
     * Get the fields every rotator screen renders.
     *
     * The integer primary key never leaves the application: uuid is the only
     * public handle, and it is what every route binds on.
     *
     * @return array<string, mixed>
     */
    private function rotatorPayload(TrafficRotator $rotator): array
    {
        return [
            'uuid' => $rotator->uuid,
            'name' => $rotator->name,
            'slug' => $rotator->slug,
            'status' => $rotator->status->value,
            'default_destination_url' => $rotator->default_destination_url,
            'created_at' => $rotator->created_at?->toIso8601String(),
        ];
    }

    /**
     * Narrow a click count to the clicks the dashboard reports as views.
     *
     * Bots are excluded here for the same reason the charts exclude them: a
     * figure the dashboard calls views has to mean the same thing everywhere
     * it appears.
     *
     * @param  Builder<TrafficRotatorClick>  $query
     */
    private function reportableClicks(Builder $query): void
    {
        $query->excludingBots();
    }
}
