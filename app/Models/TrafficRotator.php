<?php

namespace App\Models;

use App\Enums\DestinationStatus;
use App\Enums\RotatorStatus;
use App\Observers\TrafficRotatorObserver;
use App\Policies\TrafficRotatorPolicy;
use Database\Factories\TrafficRotatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property RotatorStatus $status
 * @property string|null $default_destination_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'status', 'default_destination_url'])]
#[ObservedBy(TrafficRotatorObserver::class)]
#[UsePolicy(TrafficRotatorPolicy::class)]
class TrafficRotator extends Model
{
    /** @use HasFactory<TrafficRotatorFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * The column carries the same default, but a database default only lands
     * on reload. Declaring it here means a freshly created rotator serialises
     * with the status it was actually stored with, without a round trip.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RotatorStatus::ACTIVE->value,
    ];

    /**
     * Get the columns that should receive a generated identifier.
     *
     * Only the uuid column is generated: returning it here keeps the
     * auto-incrementing integer primary key intact.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RotatorStatus::class,
        ];
    }

    /**
     * Get the user that owns the rotator.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get every destination belonging to the rotator.
     *
     * @return HasMany<TrafficRotatorDestination, $this>
     */
    public function destinations(): HasMany
    {
        return $this->hasMany(TrafficRotatorDestination::class, 'rotator_id');
    }

    /**
     * Get the destinations eligible for rotation.
     *
     * The explicit ordering is part of the rotation contract: smooth weighted
     * round robin breaks ties by the first candidate, so the sequence is only
     * reproducible while the candidate order is stable.
     *
     * @return HasMany<TrafficRotatorDestination, $this>
     */
    public function activeDestinations(): HasMany
    {
        return $this->destinations()
            ->where('status', DestinationStatus::ACTIVE)
            ->orderBy('id');
    }

    /**
     * Get every click recorded against the rotator.
     *
     * @return HasMany<TrafficRotatorClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(TrafficRotatorClick::class, 'rotator_id');
    }

    /**
     * Scope the query to rotators that are currently active.
     *
     * @param  Builder<TrafficRotator>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', RotatorStatus::ACTIVE);
    }
}
