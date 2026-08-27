<?php

namespace Database\Seeders;

use App\Enums\DestinationStatus;
use App\Enums\RotatorStatus;
use App\Models\TrafficRotator;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrafficRotatorSeeder extends Seeder
{
    /**
     * Seed the single traffic rotator served from the home route.
     */
    public function run(): void
    {
        $owner = User::query()->where('email', 'admin@sendonyx.com')->first()
            ?? User::query()->oldest('id')->firstOrFail();

        $rotator = TrafficRotator::query()->firstOrNew(['slug' => 'home']);

        $rotator->forceFill([
            'user_id' => $owner->id,
            'name' => 'Onyx Traffic Network',
            'status' => RotatorStatus::ACTIVE,
            'default_destination_url' => 'https://sendonyx.com',
        ])->save();

        if ($rotator->destinations()->exists()) {
            return;
        }

        foreach ([
            ['url' => 'https://sendonyx.com/offer-a', 'weight' => 3],
            ['url' => 'https://sendonyx.com/offer-b', 'weight' => 1],
            ['url' => 'https://sendonyx.com/offer-c', 'weight' => 1],
        ] as $destination) {
            $rotator->destinations()->create([
                ...$destination,
                'user_id' => $owner->id,
                'status' => DestinationStatus::ACTIVE,
            ]);
        }
    }
}
