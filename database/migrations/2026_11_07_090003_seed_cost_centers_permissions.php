<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'cost-centers.view', 'cost-centers.create', 'cost-centers.edit', 'cost-centers.delete',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo(self::PERMISSIONS);

        $accountant = Role::where('name', 'accountant')->where('guard_name', 'web')->first();
        $accountant?->givePermissionTo(['cost-centers.view', 'cost-centers.create', 'cost-centers.edit']);

        $viewer = Role::where('name', 'viewer')->where('guard_name', 'web')->first();
        $viewer?->givePermissionTo('cost-centers.view');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
