<?php

namespace App\Models;

use App\Enums\DeviceType;
use Database\Factories\TrafficRotatorClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rotator_id
 * @property int|null $destination_id
 * @property string $visitor_id
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property DeviceType|null $device_type
 * @property string|null $visitor_country
 * @property string|null $referrer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'rotator_id',
    'destination_id',
    'visitor_id',
    'ip_hash',
    'user_agent',
    'device_type',
    'visitor_country',
    'referrer',
])]
class TrafficRotatorClick extends Model
{
    /** @use HasFactory<TrafficRotatorClickFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
        ];
    }

    /**
     * Get the rotator the click was recorded against.
     *
     * @return BelongsTo<TrafficRotator, $this>
     */
    public function rotator(): BelongsTo
    {
        return $this->belongsTo(TrafficRotator::class, 'rotator_id');
    }

    /**
     * Get the destination the visitor was sent to.
     *
     * This is null when the rotator fell back to its default destination url.
     *
     * @return BelongsTo<TrafficRotatorDestination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(TrafficRotatorDestination::class, 'destination_id');
    }

    /**
     * Scope the query to clicks that should count towards reported statistics.
     *
     * A null device type means classification has not produced a verdict, not
     * that the visitor was a bot, so those rows are kept. Writing this as a
     * plain inequality would silently discard them under SQL's three valued
     * logic, which would hide real traffic whenever classification lags.
     *
     * @param  Builder<TrafficRotatorClick>  $query
     */
    #[Scope]
    protected function excludingBots(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('device_type')
                ->orWhere('device_type', '!=', DeviceType::BOT->value);
        });
    }
}
