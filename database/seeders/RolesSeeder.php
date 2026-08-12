<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            // Accounts
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            // Transactions / Journal entries
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete',
            'transactions.post', 'transactions.reverse', 'transactions.export',
            // Petty cash
            'petty-cash.view', 'petty-cash.create', 'petty-cash.edit', 'petty-cash.delete',
            'petty-cash.approve', 'petty-cash.approve.auditor', 'petty-cash.reconcile', 'petty-cash.import-whatsapp',
            'petty-cash.document.upload', 'petty-cash.document.delete', 'petty-cash.export',
            // Parties
            'parties.view', 'parties.create', 'parties.edit', 'parties.delete', 'parties.link-external',
            // Reports
            'reports.view', 'reports.export',
            // Settings
            'settings.view', 'settings.edit', 'settings.logo.manage',
            // Opening balances
            'opening-balances.view', 'opening-balances.edit',
            // Fiscal years
            'fiscal-years.view', 'fiscal-years.create', 'fiscal-years.close',
            'fiscal-years.reopen', 'fiscal-years.carry-forward',
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.assign-roles',
            // Backup
            'backup.view', 'backup.run', 'backup.download', 'backup.delete',
            // API tokens
            'tokens.view', 'tokens.create', 'tokens.revoke',
            // WhatsApp integration
            'whatsapp.view', 'whatsapp.manage',
            // AI assistant
            'ai.use',
            // Dashboard
            'dashboard.view',
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
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.post', 'transactions.export',
            'petty-cash.view', 'petty-cash.create', 'petty-cash.edit', 'petty-cash.reconcile',
            'petty-cash.import-whatsapp', 'petty-cash.document.upload', 'petty-cash.export',
            'parties.view', 'parties.create', 'parties.edit', 'parties.link-external',
            'reports.view', 'reports.export',
            'settings.view',
            'opening-balances.view', 'opening-balances.edit',
            'fiscal-years.view',
            'whatsapp.view',
            'ai.use',
            'dashboard.view',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'accounts.view',
            'transactions.view',
            'petty-cash.view',
            'parties.view',
            'reports.view',
            'fiscal-years.view',
            'dashboard.view',
        ]);

        $this->command->info('Roles and permissions seeded.');
    }
}
