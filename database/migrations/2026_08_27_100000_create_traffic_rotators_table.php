<?php

use App\Enums\RotatorStatus;
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
        Schema::create('traffic_rotators', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default(RotatorStatus::ACTIVE->value);
            $table->string('default_destination_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'tr_user_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_rotators');
    }
};
