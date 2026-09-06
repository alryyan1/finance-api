<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoGrantAdminRole = false;

    public function test_seeding_grants_the_admin_account_every_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('username', 'admin')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));

        $allPermissions = Permission::where('guard_name', 'web')->pluck('name');
        $this->assertNotEmpty($allPermissions);

        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $admin->hasPermissionTo($permission),
                "admin account is missing permission: {$permission}"
            );
        }
    }
}
