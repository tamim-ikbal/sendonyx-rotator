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
            // Stamped from the destination by forDestination(), the way the
            // recording job stamps them. Unattributed by default, which is
            // what a fallback hit looks like.
            'plan_uid' => null,
            'customer_uid' => null,
            'visitor_id' => bin2hex(random_bytes(16)),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_type' => DeviceType::DESKTOP,
            // Filled unconditionally so the statistics always have something
            // to aggregate. Production resolves this from the CDN header or the
            // country database, and leaves it null when neither places the hit.
            'visitor_country' => Str::upper(fake()->countryCode()),
            'referrer' => fake()->url(),
        ];
    }

    /**
     * Indicate that the click belongs to the given destination and its rotator.
     *
     * The attribution is copied off the destination here for the same reason
     * the job copies it: a click carries the plan and customer that were true
     * when it was served, so a factory that left them null would not produce
     * the rows the breakdowns actually read.
     */
    public function forDestination(TrafficRotatorDestination $destination): static
    {
        return $this->state(fn (array $attributes): array => [
            'rotator_id' => $destination->rotator_id,
            'destination_id' => $destination->id,
            'plan_uid' => $destination->plan_uid,
            'customer_uid' => $destination->customer_uid,
        ]);
    }

    /**
     * Indicate the attribution the click was stamped with, whatever its
     * destination carries now.
     *
     * This is how a test builds history that the destination has since moved
     * away from, which is the whole point of stamping.
     */
    public function attributedTo(?string $planUid, ?string $customerUid = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan_uid' => $planUid,
            'customer_uid' => $customerUid,
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
