<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_roles_with_permissions_and_user_counts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('admin'));
        $this->assertTrue($names->contains('accountant'));
        $this->assertTrue($names->contains('viewer'));

        $admin = collect($response->json())->firstWhere('name', 'admin');
        $this->assertGreaterThan(0, $admin['permissions_count'] ?? count($admin['permissions']));
        $this->assertSame(1, $admin['users_count']);
    }

    public function test_permissions_endpoint_lists_all_permission_names(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/permissions');

        $response->assertOk();
        $this->assertContains('accounts.view', $response->json());
        $this->assertContains('roles.manage', $response->json());
    }

    public function test_it_creates_a_role_with_permissions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/roles', [
            'name' => 'auditor',
            'permissions' => ['accounts.view', 'reports.view'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('roles', ['name' => 'auditor']);
        $role = Role::where('name', 'auditor')->first();
        $this->assertEqualsCanonicalizing(['accounts.view', 'reports.view'], $role->permissions->pluck('name')->all());
    }

    public function test_it_rejects_duplicate_role_names(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/roles', ['name' => 'accountant', 'permissions' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_updates_a_custom_roles_name_and_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        $role->syncPermissions(['accounts.view']);

        $response = $this->actingAs($user)->putJson("/api/roles/{$role->id}", [
            'name' => 'senior-auditor',
            'permissions' => ['accounts.view', 'accounts.edit'],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertSame('senior-auditor', $role->name);
        $this->assertEqualsCanonicalizing(['accounts.view', 'accounts.edit'], $role->permissions->pluck('name')->all());
    }

    public function test_it_refuses_to_update_the_admin_role(): void
    {
        $user = User::factory()->create();
        $admin = Role::where('name', 'admin')->first();

        $this->actingAs($user)->putJson("/api/roles/{$admin->id}", [
            'name' => 'super-admin',
            'permissions' => [],
        ])->assertStatus(422);

        $this->assertSame('admin', $admin->fresh()->name);
    }

    public function test_it_refuses_to_delete_the_admin_role(): void
    {
        $user = User::factory()->create();
        $admin = Role::where('name', 'admin')->first();

        $this->actingAs($user)->deleteJson("/api/roles/{$admin->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_it_refuses_to_delete_a_role_still_assigned_to_users(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        $other = User::factory()->create();
        $other->assignRole('auditor');

        $this->actingAs($user)->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('roles', ['name' => 'auditor']);
    }

    public function test_it_deletes_an_unassigned_custom_role(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->actingAs($user)->deleteJson("/api/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['name' => 'auditor']);
    }
}
