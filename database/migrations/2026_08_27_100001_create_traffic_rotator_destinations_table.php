<?php

use App\Enums\DestinationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('traffic_rotator_destinations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('rotator_id')->constrained('traffic_rotators')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->unsignedTinyInteger('weight')->default(1);
            $table->string('status')->default(DestinationStatus::ACTIVE->value);
            $table->timestamps();

            $table->index(['rotator_id', 'status', 'id'], 'trd_rotator_status_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_rotator_destinations');
    }
};
