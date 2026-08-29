<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('traffic_rotator_clicks', function (Blueprint $table): void {
            // Copied off the destination at record time rather than joined back
            // to it at read time, so a destination moving to another plan
            // leaves its earned traffic where it was earned.
            $table->string('plan_uid')->nullable()->after('destination_id');
            $table->string('customer_uid')->nullable()->after('plan_uid');

            // device_type is the third column on purpose: the breakdowns filter
            // bots out, and without it in the index every grouped row has to be
            // fetched from the table to read that one column. With it, the
            // whole aggregate is served from the index.
            $table->index(['rotator_id', 'plan_uid', 'device_type'], 'trc_rotator_plan_device_index');
            $table->index(['rotator_id', 'customer_uid', 'device_type'], 'trc_rotator_customer_device_index');
        });

        $this->backfillFromDestinations();

        Schema::table('traffic_rotator_destinations', function (Blueprint $table): void {
            // These existed only to serve the join the breakdowns no longer do.
            // Nothing reads them now, and an index nobody reads is write cost.
            $table->dropIndex('trd_rotator_plan_uid_index');
            $table->dropIndex('trd_rotator_customer_uid_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_rotator_destinations', function (Blueprint $table): void {
            $table->index(['rotator_id', 'plan_uid'], 'trd_rotator_plan_uid_index');
            $table->index(['rotator_id', 'customer_uid'], 'trd_rotator_customer_uid_index');
        });

        Schema::table('traffic_rotator_clicks', function (Blueprint $table): void {
            $table->dropIndex('trc_rotator_plan_device_index');
            $table->dropIndex('trc_rotator_customer_device_index');
            $table->dropColumn(['plan_uid', 'customer_uid']);
        });
    }

    /**
     * Stamp existing clicks with the attribution their destination carries now.
     *
     * This reproduces exactly what the join used to return, so no history
     * changes meaning on the way through. Only clicks recorded from here on
     * carry attribution that is genuinely point in time.
     *
     * Written as a correlated subquery rather than an UPDATE ... JOIN because
     * SQLite has no join form of UPDATE and the suite runs on SQLite.
     */
    private function backfillFromDestinations(): void
    {
        DB::table('traffic_rotator_clicks')
            ->whereNotNull('destination_id')
            ->update([
                'plan_uid' => DB::raw('(select plan_uid from traffic_rotator_destinations where traffic_rotator_destinations.id = traffic_rotator_clicks.destination_id)'),
                'customer_uid' => DB::raw('(select customer_uid from traffic_rotator_destinations where traffic_rotator_destinations.id = traffic_rotator_clicks.destination_id)'),
            ]);
    }
};
