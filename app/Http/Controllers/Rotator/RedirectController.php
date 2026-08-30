<?php

namespace App\Http\Controllers\Rotator;

use App\Http\Controllers\Controller;
use App\Jobs\RecordRotatorClick;
use App\Support\Rotation\DestinationResolver;
use App\Support\Rotation\RotatorCache;
use App\Support\Rotation\VisitorIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public face of the rotator: one hit in, one redirect out.
 */
final class RedirectController extends Controller
{
    public function __construct(
        private readonly RotatorCache $rotators,
        private readonly DestinationResolver $resolver,
        private readonly VisitorIdentity $visitors,
    ) {}

    /**
     * Send the visitor to the next destination in the rotation.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $snapshot = $this->rotators->snapshot();

        abort_if($snapshot === null, Response::HTTP_NOT_FOUND);

        $decision = $this->resolver->resolve($snapshot);

        abort_if($decision === null, Response::HTTP_NOT_FOUND);

        $visitorId = $this->visitors->resolve($request);

        // Everything here is read off the request and nothing is derived from
        // it: device parsing and the country lookup both belong to the job. The
        // CDN's country header is the one exception that has to be collected
        // now, because it exists only on the request, and reading it is a
        // string lookup rather than work.
        RecordRotatorClick::dispatch(
            $decision->rotatorId,
            $decision->destinationId,
            $visitorId,
            $request->ip(),
            $request->userAgent(),
            $request->headers->get('referer'),
            $request->headers->get(config()->string('rotator.geo.header')),
        );

        // A cached redirect would serve one visitor's destination to everybody
        // downstream of the cache, which is the one failure mode a rotator
        // cannot tolerate.
        return redirect()->away($decision->url, Response::HTTP_FOUND)
            ->withCookie($this->visitors->cookie($visitorId))
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }
}
