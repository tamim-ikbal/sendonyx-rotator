<?php

namespace App\Http\Resources;

use App\Models\TrafficRotatorDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrafficRotatorDestination
 */
class TrafficRotatorDestinationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'weight' => $this->weight,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
