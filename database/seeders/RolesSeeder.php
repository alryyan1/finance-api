<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            // Accounts
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            // Transactions
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete', 'transactions.post',
            // Cash vouchers
            'cash-vouchers.view', 'cash-vouchers.create', 'cash-vouchers.delete',
            // Petty cash
            'petty-cash.view', 'petty-cash.manage', 'petty-cash.approve',
            // Parties
            'parties.view', 'parties.create', 'parties.edit', 'parties.delete',
            // Reports
            'reports.view',
            // Settings
            'settings.view', 'settings.edit',
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete',
            // Backup
            'backup.run', 'backup.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions); // all permissions

        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'accounts.view', 'accounts.create', 'accounts.edit',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.post',
            'cash-vouchers.view', 'cash-vouchers.create',
            'petty-cash.view', 'petty-cash.manage',
            'parties.view', 'parties.create', 'parties.edit',
            'reports.view',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'accounts.view',
            'transactions.view',
            'cash-vouchers.view',
            'petty-cash.view',
            'parties.view',
            'reports.view',
        ]);

        $this->command->info('Roles and permissions seeded.');
    }
}
