<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions that replace the unused legacy "cash-vouchers.*" group.
     *
     * @var array<int, string>
     */
    private array $legacyCashVoucherPermissions = [
        'cash-vouchers.view', 'cash-vouchers.create', 'cash-vouchers.delete',
    ];

    /**
     * All newly introduced permissions, grouped by the role that should receive them.
     *
     * @var array<string, array<int, string>>
     */
    private array $newPermissionsByRole = [
        'admin' => [
            'transactions.reverse', 'transactions.export',
            'petty-cash.view', 'petty-cash.create', 'petty-cash.edit', 'petty-cash.delete',
            'petty-cash.approve', 'petty-cash.reconcile', 'petty-cash.import-whatsapp',
            'petty-cash.document.upload', 'petty-cash.document.delete', 'petty-cash.export',
            'parties.link-external',
            'reports.export',
            'settings.logo.manage',
            'opening-balances.view', 'opening-balances.edit',
            'fiscal-years.view', 'fiscal-years.create', 'fiscal-years.close',
            'fiscal-years.reopen', 'fiscal-years.carry-forward',
            'users.assign-roles',
            'backup.download', 'backup.delete',
            'tokens.view', 'tokens.create', 'tokens.revoke',
            'whatsapp.view', 'whatsapp.manage',
            'ai.use',
            'dashboard.view',
        ],
        'accountant' => [
            'transactions.export',
            'petty-cash.view', 'petty-cash.create', 'petty-cash.edit', 'petty-cash.reconcile',
            'petty-cash.import-whatsapp', 'petty-cash.document.upload', 'petty-cash.export',
            'parties.link-external',
            'reports.export',
            'opening-balances.view', 'opening-balances.edit',
            'fiscal-years.view',
            'whatsapp.view',
            'ai.use',
            'dashboard.view',
        ],
        'viewer' => [
            'petty-cash.view',
            'fiscal-years.view',
            'dashboard.view',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->newPermissionsByRole as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            }

            $role->givePermissionTo($permissions);
        }

        // Drop the legacy "cash-vouchers.*" group, superseded by "petty-cash.*".
        $legacy = Permission::whereIn('name', $this->legacyCashVoucherPermissions)
            ->where('guard_name', 'web')
            ->get();

        foreach ($legacy as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->newPermissionsByRole as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo($permissions);
        }

        $allNewPermissions = collect($this->newPermissionsByRole)->flatten()->unique();
        Permission::whereIn('name', $allNewPermissions)->where('guard_name', 'web')->delete();

        foreach ($this->legacyCashVoucherPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo($this->legacyCashVoucherPermissions);

        $accountant = Role::where('name', 'accountant')->where('guard_name', 'web')->first();
        $accountant?->givePermissionTo(['cash-vouchers.view', 'cash-vouchers.create']);

        $viewer = Role::where('name', 'viewer')->where('guard_name', 'web')->first();
        $viewer?->givePermissionTo(['cash-vouchers.view']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
