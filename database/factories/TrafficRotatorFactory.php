<?php

namespace Database\Factories;

use App\Enums\RotatorStatus;
use App\Models\TrafficRotator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrafficRotator>
 */
class TrafficRotatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->domainWord().' rotator';

        return [
            'user_id' => User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'status' => RotatorStatus::ACTIVE,
            'default_destination_url' => null,
        ];
    }

    /**
     * Indicate that the rotator is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RotatorStatus::PAUSED,
        ]);
    }

    /**
     * Indicate that the rotator falls back to the given url.
     */
    public function withDefaultUrl(string $url = 'https://sendonyx.com/fallback'): static
    {
        return $this->state(fn (array $attributes): array => [
            'default_destination_url' => $url,
        ]);
    }
}
