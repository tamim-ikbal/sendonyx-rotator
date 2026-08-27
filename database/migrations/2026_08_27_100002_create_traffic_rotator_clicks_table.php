<?php

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
        Schema::create('traffic_rotator_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rotator_id')->constrained('traffic_rotators')->cascadeOnDelete();

            // A null destination_id means the rotator fell back to its default
            // destination url. That sentinel is why this column cascades rather
            // than nullifies: nullOnDelete would silently rewrite a deleted
            // destination's history into phantom fallback hits.
            $table->foreignId('destination_id')->nullable()
                ->constrained('traffic_rotator_destinations')->cascadeOnDelete();

            $table->string('visitor_id', 100);
            $table->char('ip_hash', 64);
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->char('visitor_country', 2)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->timestamps();

            // Index names are set explicitly: the generated name for a three
            // column index on this table exceeds MySQL's 64 character limit.
            $table->index(['destination_id', 'created_at'], 'trc_destination_created_index');
            $table->index(['rotator_id', 'created_at'], 'trc_rotator_created_index');
            $table->index('device_type', 'trc_device_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_rotator_clicks');
    }
};
