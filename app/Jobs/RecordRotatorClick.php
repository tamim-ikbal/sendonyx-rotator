<?php

namespace App\Jobs;

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Support\Geo\CountryResolver;
use App\Support\UserAgent\DeviceTypeResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes one redirect to the click log, off the request's critical path.
 *
 * The payload is scalars only, and deliberately so. SerializesModels would
 * store model keys and re-query them when the worker unserializes the job,
 * turning one asynchronous insert back into several synchronous reads.
 *
 * Everything that costs more than reading a request header is done here rather
 * than in the controller, so the redirect is a rotation decision and nothing
 * else: user agent parsing, and the country lookup for any hit whose country
 * the CDN did not already state.
 */
class RecordRotatorClick implements ShouldQueue
{
    use Queueable;

    /**
     * The largest referrer the column can hold.
     */
    private const REFERRER_LENGTH = 2048;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $rotatorId,
        public readonly ?int $destinationId,
        public readonly string $visitorId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $referrer,
        public readonly ?string $cdnCountry = null,
    ) {
        $this->onQueue(config()->string('rotator.queue'));
    }

    /**
     * Execute the job.
     *
     * Both parents are confirmed before the insert. A rotator or destination
     * deleted between the redirect and the worker picking this up takes its
     * clicks with it through the foreign keys, so writing the row would either
     * fail outright or resurrect history that was meant to be gone.
     */
    public function handle(DeviceTypeResolver $devices, CountryResolver $countries): void
    {
        if (! TrafficRotator::query()->whereKey($this->rotatorId)->exists()) {
            return;
        }

        $attribution = $this->attribution();

        if ($attribution === null) {
            return;
        }

        TrafficRotatorClick::query()->create([
            'rotator_id' => $this->rotatorId,
            'destination_id' => $this->destinationId,
            ...$attribution,
            'visitor_id' => $this->visitorId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'device_type' => $devices->resolve($this->userAgent),
            'visitor_country' => $countries->resolve($this->cdnCountry, $this->ipAddress),
            'referrer' => $this->truncatedReferrer(),
        ]);
    }

    /**
     * Read the plan and customer to stamp on this click.
     *
     * Stamping is what makes the traffic breakdowns point in time: the click
     * keeps the attribution the destination had when it was served, so
     * reassigning a destination later cannot move history off the plan that
     * earned it.
     *
     * This doubles as the destination's existence check, which is why it costs
     * nothing: the same primary key lookup that used to return a boolean now
     * returns the two values as well. A null return means the destination is
     * gone and the click must not be written.
     *
     * @return array{plan_uid: string|null, customer_uid: string|null}|null
     */
    private function attribution(): ?array
    {
        // A fallback hit had no destination to inherit from, so it is recorded
        // unattributed rather than rejected.
        if ($this->destinationId === null) {
            return ['plan_uid' => null, 'customer_uid' => null];
        }

        $destination = TrafficRotatorDestination::query()
            ->whereKey($this->destinationId)
            ->first(['plan_uid', 'customer_uid']);

        if ($destination === null) {
            return null;
        }

        return [
            'plan_uid' => $destination->plan_uid,
            'customer_uid' => $destination->customer_uid,
        ];
    }

    /**
     * Cut the referrer down to what the column accepts.
     *
     * Referrers are attacker controlled and unbounded in length, and an
     * oversized value would fail the insert under a strict SQL mode.
     */
    private function truncatedReferrer(): ?string
    {
        if ($this->referrer === null) {
            return null;
        }

        return mb_substr($this->referrer, 0, self::REFERRER_LENGTH);
    }
}
