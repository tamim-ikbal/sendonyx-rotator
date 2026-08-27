<?php

namespace App\Models;

use App\Enums\DestinationStatus;
use App\Observers\TrafficRotatorDestinationObserver;
use Database\Factories\TrafficRotatorDestinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
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
 * @property int $rotator_id
 * @property int $user_id
 * @property string $url
 * @property int $weight
 * @property DestinationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['url', 'weight', 'status'])]
#[ObservedBy(TrafficRotatorDestinationObserver::class)]
class TrafficRotatorDestination extends Model
{
    /** @use HasFactory<TrafficRotatorDestinationFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * These mirror the column defaults so a freshly created destination
     * serialises with the weight and status it was stored with, rather than
     * with nulls that only a reload would fill in.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'weight' => 1,
        'status' => DestinationStatus::ACTIVE->value,
    ];

    /**
     * Get the columns that should receive a generated identifier.
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
            'weight' => 'integer',
            'status' => DestinationStatus::class,
        ];
    }

    /**
     * Get the rotator the destination belongs to.
     *
     * @return BelongsTo<TrafficRotator, $this>
     */
    public function rotator(): BelongsTo
    {
        return $this->belongsTo(TrafficRotator::class, 'rotator_id');
    }

    /**
     * Get the user that owns the destination.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get every click recorded against the destination.
     *
     * @return HasMany<TrafficRotatorClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(TrafficRotatorClick::class, 'destination_id');
    }

    /**
     * Scope the query to destinations eligible for rotation.
     *
     * @param  Builder<TrafficRotatorDestination>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', DestinationStatus::ACTIVE);
    }
}
