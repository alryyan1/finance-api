<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The seeded/default admin account (see database/seeders/DatabaseSeeder.php)
     * had no role assigned anywhere — permission enforcement would otherwise
     * lock it out entirely. Grandfather it into the "admin" role.
     */
    private string $username = 'admin';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = User::where('username', $this->username)->first();

        if (! $user) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $user = User::where('username', $this->username)->first();

        $user?->removeRole('admin');
    }
};
