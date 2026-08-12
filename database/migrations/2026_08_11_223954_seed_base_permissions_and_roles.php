<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The original permission set that historically only existed in
     * database\seeders\RolesSeeder.php and was never actually run against
     * this database (the seeder was not wired into DatabaseSeeder and
     * production deploys only run `migrate`, not `db:seed`).
     *
     * @var array<int, string>
     */
    private array $basePermissions = [
        'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
        'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete', 'transactions.post',
        'parties.view', 'parties.create', 'parties.edit', 'parties.delete',
        'reports.view',
        'settings.view', 'settings.edit',
        'users.view', 'users.create', 'users.edit', 'users.delete',
        'backup.run', 'backup.view',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $basePermissionsByRole = [
        'accountant' => [
            'accounts.view', 'accounts.create', 'accounts.edit',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.post',
            'parties.view', 'parties.create', 'parties.edit',
            'reports.view',
        ],
        'viewer' => [
            'accounts.view',
            'transactions.view',
            'parties.view',
            'reports.view',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->basePermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($this->basePermissions);

        foreach ($this->basePermissionsByRole as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->revokePermissionTo($this->basePermissions);

        foreach ($this->basePermissionsByRole as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo($permissions);
        }

        Permission::whereIn('name', $this->basePermissions)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
