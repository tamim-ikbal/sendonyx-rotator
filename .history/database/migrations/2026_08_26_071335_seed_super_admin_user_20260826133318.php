<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The super admin email address.
     */
    private const EMAIL = 'admin@sendonyx.com';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = User::query()->firstOrNew(['email' => self::EMAIL]);

        $user->forceFill([
            'name' => 'Super Admin',
            'password' => 'password',
            'role' => UserRole::SUPER_ADMIN,
            'email_verified_at' => now(),
        ])->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        User::query()->where('email', self::EMAIL)->delete();
    }
};
