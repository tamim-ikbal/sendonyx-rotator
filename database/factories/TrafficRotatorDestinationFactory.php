<?php

namespace Database\Factories;

use App\Enums\DestinationStatus;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrafficRotatorDestination>
 */
class TrafficRotatorDestinationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rotator_id' => TrafficRotator::factory(),
            'user_id' => User::factory(),
            'url' => fake()->url(),
            'weight' => 1,
            'status' => DestinationStatus::ACTIVE,
        ];
    }

    /**
     * Indicate that the destination is paused and excluded from rotation.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DestinationStatus::PAUSED,
        ]);
    }

    /**
     * Indicate the rotation weight of the destination.
     */
    public function weight(int $weight): static
    {
        return $this->state(fn (array $attributes): array => [
            'weight' => $weight,
        ]);
    }

    /**
     * Indicate the rotator and owner the destination belongs to.
     */
    public function forRotator(TrafficRotator $rotator): static
    {
        return $this->state(fn (array $attributes): array => [
            'rotator_id' => $rotator->id,
            'user_id' => $rotator->user_id,
        ]);
    }
}
