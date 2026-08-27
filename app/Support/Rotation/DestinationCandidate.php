<?php

namespace App\Support\Rotation;

/**
 * One destination eligible for rotation, reduced to what the hot path needs.
 *
 * Snapshots are cached, so this deliberately holds scalars rather than an
 * Eloquent model: unserializing a model on every request would defeat the point
 * of caching, and a stale hydrated model is far easier to misuse than a value.
 */
final readonly class DestinationCandidate
{
    public function __construct(
        public int $id,
        public string $url,
        public int $weight,
    ) {}

    /**
     * Build a candidate from its cached representation.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['url'] ?? ''),
            (int) ($payload['weight'] ?? 0),
        );
    }

    /**
     * Reduce the candidate to the plain values that go into the cache.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'weight' => $this->weight,
        ];
    }
}
