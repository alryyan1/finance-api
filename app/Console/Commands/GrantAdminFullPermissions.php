<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GrantAdminFullPermissions extends Command
{
    protected $signature = 'permissions:grant-admin
                            {--guard=web : Guard name to sync permissions for}
                            {--user=test@example.com : Email of the user to assign the admin role to}';

    protected $description = 'Sync the admin role to every existing permission, so it never drifts behind newly added permissions';

    public function handle(): int
    {
        $guard = $this->option('guard');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $permissions = Permission::where('guard_name', $guard)->pluck('name');

        $admin->syncPermissions($permissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("Admin role now has all {$permissions->count()} permission(s) for guard \"{$guard}\".");

        $email = $this->option('user');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->warn("No user found with email \"{$email}\" — skipped role assignment.");

            return self::SUCCESS;
        }

        $user->syncRoles([$admin]);
        $this->info("Assigned the admin role to \"{$user->email}\".");

        return self::SUCCESS;
    }
}
