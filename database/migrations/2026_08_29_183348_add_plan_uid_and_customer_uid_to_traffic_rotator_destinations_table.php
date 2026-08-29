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
        Schema::table('traffic_rotator_destinations', function (Blueprint $table): void {
            // Identifiers owned by whatever system sold the plan and the seat.
            // They are opaque here: nothing joins on them, they are only ever
            // grouped by, which is why they carry no foreign key.
            $table->string('plan_uid')->nullable()->after('url');
            $table->string('customer_uid')->nullable()->after('plan_uid');

            // The traffic breakdowns group a single rotator's destinations by
            // one of these, so the rotator leads both indexes.
            $table->index(['rotator_id', 'plan_uid'], 'trd_rotator_plan_uid_index');
            $table->index(['rotator_id', 'customer_uid'], 'trd_rotator_customer_uid_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_rotator_destinations', function (Blueprint $table): void {
            $table->dropIndex('trd_rotator_plan_uid_index');
            $table->dropIndex('trd_rotator_customer_uid_index');
            $table->dropColumn(['plan_uid', 'customer_uid']);
        });
    }
};
