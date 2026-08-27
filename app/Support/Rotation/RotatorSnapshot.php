<?php

namespace App\Support\Rotation;

/**
 * A cached view of the rotator the redirect route serves.
 *
 * Holds the rotator's identity, its fallback url and its rotation candidates in
 * the order the rotation contract requires, which is destination id ascending.
 *
 * A snapshot crosses the cache as the plain array toArray() produces, never as
 * an object. Laravel refuses to unserialize classes out of a cache store by
 * default, so an object here would come back as __PHP_Incomplete_Class on every
 * store that actually serializes.
 */
final readonly class RotatorSnapshot
{
    /**
     * @param  array<int, DestinationCandidate>  $candidates  Keyed by destination id, ordered by id ascending.
     */
    public function __construct(
        public int $rotatorId,
        public ?string $defaultDestinationUrl,
        public array $candidates,
    ) {}

    /**
     * Rebuild a snapshot from its cached representation.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $candidates = [];

        /** @var array<int, mixed> $cached */
        $cached = is_array($payload['candidates'] ?? null) ? $payload['candidates'] : [];

        foreach ($cached as $id => $candidate) {
            if (is_array($candidate)) {
                $candidates[(int) $id] = DestinationCandidate::fromArray($candidate);
            }
        }

        $default = $payload['default_destination_url'] ?? null;

        return new self(
            (int) ($payload['rotator_id'] ?? 0),
            is_string($default) ? $default : null,
            $candidates,
        );
    }

    /**
     * Reduce the snapshot to the plain values that go into the cache.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rotator_id' => $this->rotatorId,
            'default_destination_url' => $this->defaultDestinationUrl,
            'candidates' => array_map(
                static fn (DestinationCandidate $candidate): array => $candidate->toArray(),
                $this->candidates,
            ),
        ];
    }

    /**
     * Determine whether the rotator has anything to rotate between.
     */
    public function hasCandidates(): bool
    {
        return $this->candidates !== [];
    }

    /**
     * Get the weights to hand the rotation state store.
     *
     * The ordering carries meaning: smooth weighted round robin breaks ties on
     * the first candidate, so this must preserve the ascending id order.
     *
     * @return array<int, int>
     */
    public function weights(): array
    {
        return array_map(
            static fn (DestinationCandidate $candidate): int => $candidate->weight,
            $this->candidates,
        );
    }

    /**
     * Get the candidate with the given id, if it is still in the snapshot.
     */
    public function candidate(int $destinationId): ?DestinationCandidate
    {
        return $this->candidates[$destinationId] ?? null;
    }
}
