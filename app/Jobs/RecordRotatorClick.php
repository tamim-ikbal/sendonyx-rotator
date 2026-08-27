<?php

namespace App\Jobs;

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Support\UserAgent\DeviceTypeResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes one redirect to the click log, off the request's critical path.
 *
 * The payload is scalars only, and deliberately so. SerializesModels would
 * store model keys and re-query them when the worker unserializes the job,
 * turning one asynchronous insert back into several synchronous reads.
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
        public readonly string $ipHash,
        public readonly ?string $userAgent,
        public readonly ?string $referrer,
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
    public function handle(DeviceTypeResolver $devices): void
    {
        if (! $this->parentsStillExist()) {
            return;
        }

        TrafficRotatorClick::query()->create([
            'rotator_id' => $this->rotatorId,
            'destination_id' => $this->destinationId,
            'visitor_id' => $this->visitorId,
            'ip_hash' => $this->ipHash,
            'user_agent' => $this->userAgent,
            'device_type' => $devices->resolve($this->userAgent),
            'referrer' => $this->truncatedReferrer(),
        ]);
    }

    /**
     * Determine whether the rotator, and the destination if there was one, remain.
     */
    private function parentsStillExist(): bool
    {
        if (! TrafficRotator::query()->whereKey($this->rotatorId)->exists()) {
            return false;
        }

        if ($this->destinationId === null) {
            return true;
        }

        return TrafficRotatorDestination::query()->whereKey($this->destinationId)->exists();
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
