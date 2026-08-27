<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::CUSTOMER,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user should have the given role.
     */
    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     *
     * Requires the Fortify two_factor_* columns, which this application has not
     * migrated yet, so this state is only usable once that migration lands.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt('two-factor-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
