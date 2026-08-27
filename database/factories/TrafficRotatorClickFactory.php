<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrafficRotatorClick>
 */
class TrafficRotatorClickFactory extends Factory
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
            'destination_id' => TrafficRotatorDestination::factory(),
            'visitor_id' => bin2hex(random_bytes(16)),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'device_type' => DeviceType::DESKTOP,
            // Production leaves this null until geo detection lands; the
            // factory fills it so statistics have something to aggregate.
            'visitor_country' => Str::upper(fake()->countryCode()),
            'referrer' => fake()->url(),
        ];
    }

    /**
     * Indicate that the click belongs to the given destination and its rotator.
     */
    public function forDestination(TrafficRotatorDestination $destination): static
    {
        return $this->state(fn (array $attributes): array => [
            'rotator_id' => $destination->rotator_id,
            'destination_id' => $destination->id,
        ]);
    }

    /**
     * Indicate that the rotator fell back to its default destination url.
     */
    public function fallback(TrafficRotator $rotator): static
    {
        return $this->state(fn (array $attributes): array => [
            'rotator_id' => $rotator->id,
            'destination_id' => null,
        ]);
    }

    /**
     * Indicate the moment the click was recorded.
     */
    public function at(CarbonImmutable $moment): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);
    }

    /**
     * Indicate the visitor the click is attributed to.
     */
    public function fromVisitor(string $visitorId): static
    {
        return $this->state(fn (array $attributes): array => [
            'visitor_id' => $visitorId,
        ]);
    }

    /**
     * Indicate the device the click came from.
     */
    public function device(DeviceType $device): static
    {
        return $this->state(fn (array $attributes): array => [
            'device_type' => $device,
        ]);
    }

    /**
     * Indicate that the click came from a bot.
     */
    public function bot(): static
    {
        return $this->device(DeviceType::BOT);
    }

    /**
     * Indicate that the click has not been classified yet.
     */
    public function unclassified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'device_type' => null,
        ]);
    }

    /**
     * Indicate the country the visitor was located in.
     */
    public function fromCountry(?string $country): static
    {
        return $this->state(fn (array $attributes): array => [
            'visitor_country' => $country,
        ]);
    }
}
