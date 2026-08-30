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
        Schema::table('traffic_rotator_clicks', function (Blueprint $table): void {
            // Nullable because Request::ip() is: a hit with no resolvable
            // client address is still logged rather than dropped. 45 characters
            // is the longest an IPv4 mapped IPv6 address can be.
            $table->string('ip_address', 45)->nullable()->after('visitor_id');
        });

        // Dropped in its own statement: SQLite rebuilds the table to remove a
        // column, and the suite runs on SQLite.
        Schema::table('traffic_rotator_clicks', function (Blueprint $table): void {
            // Nothing ever read this column. It was written on every click and
            // never queried, and now that the address it hashes is stored in
            // plaintext beside it, the hash protects nothing it did not already
            // fail to protect.
            $table->dropColumn('ip_hash');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The hashes themselves cannot come back, so the column returns nullable
     * rather than as the NOT NULL char(64) it was.
     */
    public function down(): void
    {
        Schema::table('traffic_rotator_clicks', function (Blueprint $table): void {
            $table->char('ip_hash', 64)->nullable()->after('visitor_id');
        });

        Schema::table('traffic_rotator_clicks', function (Blueprint $table): void {
            $table->dropColumn('ip_address');
        });
    }
};
