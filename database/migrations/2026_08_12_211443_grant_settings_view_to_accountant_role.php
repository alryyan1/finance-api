<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The accountant role can create/edit/reconcile petty cash transactions, but
 * GET /api/settings (which serves the petty-cash cash/bank account config,
 * manager/auditor bindings, etc.) was gated behind settings.view — a
 * permission accountant never had. That silently broke the petty cash page
 * for every accountant-role user (it read as "accounts not configured").
 */
return new class extends Migration
{
    private const PERMISSION = 'settings.view';

    private const ROLE = 'accountant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();
        $role?->givePermissionTo(self::PERMISSION);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();
        $role?->revokePermissionTo(self::PERMISSION);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
