<?php

namespace App\Support\Stats;

use App\Enums\IndicatorPosition;

/**
 * A metric's movement against the immediately preceding equal length period.
 */
final readonly class Indicator
{
    private function __construct(
        public IndicatorPosition $position,
        public ?float $rate,
    ) {}

    /**
     * Compare a value against its baseline.
     *
     * The direction comes from comparing the two values, not from the sign of
     * the change: the rate is reported as a magnitude, and reading direction
     * back out of it would invert every fall.
     *
     * A range with no baseline reports a null rate rather than 0, so a caller
     * can tell "did not move" apart from "there is nothing to compare with".
     */
    public static function between(float $current, float $previous, bool $hasBaseline = true): self
    {
        if (! $hasBaseline) {
            return new self(IndicatorPosition::FLAT, null);
        }

        $position = match (true) {
            $current > $previous => IndicatorPosition::UP,
            $current < $previous => IndicatorPosition::DOWN,
            default => IndicatorPosition::FLAT,
        };

        // Growth from nothing has no percentage. Reporting a full 100 is the
        // convention the dashboard expects; dividing would be a fatal.
        if ($previous === 0.0) {
            return new self($position, $current > 0.0 ? 100.0 : 0.0);
        }

        return new self($position, round(abs($current - $previous) / abs($previous) * 100, 1));
    }

    /**
     * @return array{rate: float|null, position: string}
     */
    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
            'position' => $this->position->value,
        ];
    }
}
