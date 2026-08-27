<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the traffic rotators owned by the user.
     *
     * @return HasMany<TrafficRotator, $this>
     */
    public function rotators(): HasMany
    {
        return $this->hasMany(TrafficRotator::class);
    }

    /**
     * Determine if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine if the user has administrative access.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true);
    }

    /**
     * Determine if the user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }
}
