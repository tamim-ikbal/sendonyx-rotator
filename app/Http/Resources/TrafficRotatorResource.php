<?php

namespace App\Http\Resources;

use App\Models\TrafficRotator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrafficRotator
 */
class TrafficRotatorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The integer primary key never leaves the application: uuid is the only
     * public handle, and it is what every route binds on.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'default_destination_url' => $this->default_destination_url,
            'destinations_count' => $this->whenCounted('destinations'),
            'destinations' => TrafficRotatorDestinationResource::collection(
                $this->whenLoaded('destinations'),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
