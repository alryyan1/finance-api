<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Exercises every `permission:parties.*` middleware gate on the parties
 * routes (routes/api.php) in isolation: a user with none of the permissions
 * must be denied (403), and a user with only the specific permission under
 * test must be allowed through.
 */
class PartyPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests specifically exercise the permission system — don't
        // let the shared TestCase auto-grant the admin role via actingAs().
        $this->autoGrantAdminRole = false;

        foreach (['parties.view', 'parties.create', 'parties.edit', 'parties.delete', 'parties.link-external'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
    }

    private function userWithOnly(?string $permission): User
    {
        $user = User::factory()->create();
        if ($permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function assertForbidden($response): void
    {
        $response->assertStatus(403);
        $response->assertExactJson(['message' => 'ليس لديك الصلاحية اللازمة للقيام بهذا الإجراء.']);
    }

    private function makeParty(array $overrides = []): Party
    {
        return Party::create(array_merge([
            'name' => 'Ahmed',
            'type' => 'customer',
            'is_active' => true,
        ], $overrides));
    }

    // ── parties.view (index / show) ─────────────────────────────────────────

    public function test_index_denied_without_view_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/parties'));
    }

    public function test_index_allowed_with_view_permission(): void
    {
        $this->actingAs($this->userWithOnly('parties.view'))
            ->getJson('/api/parties')->assertOk();
    }

    public function test_show_denied_without_view_permission(): void
    {
        $party = $this->makeParty();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson("/api/parties/{$party->id}"));
    }

    public function test_show_allowed_with_view_permission(): void
    {
        $party = $this->makeParty();
        $this->actingAs($this->userWithOnly('parties.view'))
            ->getJson("/api/parties/{$party->id}")
            ->assertOk()
            ->assertJsonPath('id', $party->id);
    }

    // ── parties.create (store) ──────────────────────────────────────────────

    public function test_store_denied_without_create_permission(): void
    {
        $response = $this->actingAs($this->userWithOnly(null))->postJson('/api/parties', [
            'name' => 'Blocked', 'type' => 'customer',
        ]);
        $this->assertForbidden($response);
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_store_allowed_with_create_permission(): void
    {
        $this->actingAs($this->userWithOnly('parties.create'))->postJson('/api/parties', [
            'name' => 'Allowed', 'type' => 'customer',
        ])->assertCreated();
        $this->assertDatabaseCount('parties', 1);
    }

    // ── parties.edit (update) ───────────────────────────────────────────────

    public function test_update_denied_without_edit_permission(): void
    {
        $party = $this->makeParty();
        $response = $this->actingAs($this->userWithOnly(null))->putJson("/api/parties/{$party->id}", [
            'name' => 'Blocked update', 'type' => 'customer',
        ]);
        $this->assertForbidden($response);
        $this->assertSame('Ahmed', $party->fresh()->name);
    }

    public function test_update_allowed_with_edit_permission(): void
    {
        $party = $this->makeParty();
        $this->actingAs($this->userWithOnly('parties.edit'))->putJson("/api/parties/{$party->id}", [
            'name' => 'Allowed update', 'type' => 'customer',
        ])->assertOk();
        $this->assertSame('Allowed update', $party->fresh()->name);
    }

    // ── parties.delete (destroy) ────────────────────────────────────────────

    public function test_destroy_denied_without_delete_permission(): void
    {
        $party = $this->makeParty();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->deleteJson("/api/parties/{$party->id}"));
        $this->assertDatabaseHas('parties', ['id' => $party->id]);
    }

    public function test_destroy_allowed_with_delete_permission(): void
    {
        $party = $this->makeParty();
        $this->actingAs($this->userWithOnly('parties.delete'))
            ->deleteJson("/api/parties/{$party->id}")->assertNoContent();
        $this->assertDatabaseMissing('parties', ['id' => $party->id]);
    }

    // ── parties.link-external (resolve-external) ────────────────────────────

    public function test_resolve_external_denied_without_link_external_permission(): void
    {
        $response = $this->actingAs($this->userWithOnly(null))->postJson('/api/parties/resolve-external', [
            'source_system' => 'sales-api',
            'source_type' => 'customer',
            'source_id' => 'ext-1',
            'name' => 'Blocked External',
        ]);
        $this->assertForbidden($response);
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_resolve_external_allowed_with_link_external_permission(): void
    {
        $this->actingAs($this->userWithOnly('parties.link-external'))->postJson('/api/parties/resolve-external', [
            'source_system' => 'sales-api',
            'source_type' => 'customer',
            'source_id' => 'ext-1',
            'name' => 'Allowed External',
        ])->assertOk();
        $this->assertDatabaseCount('parties', 1);
    }

    // ── Cross-permission isolation ──────────────────────────────────────────

    public function test_view_permission_alone_does_not_allow_create(): void
    {
        $response = $this->actingAs($this->userWithOnly('parties.view'))->postJson('/api/parties', [
            'name' => 'Should be blocked', 'type' => 'customer',
        ]);
        $this->assertForbidden($response);
    }

    public function test_create_permission_alone_does_not_allow_delete(): void
    {
        $party = $this->makeParty();
        $this->assertForbidden($this->actingAs($this->userWithOnly('parties.create'))->deleteJson("/api/parties/{$party->id}"));
    }

    public function test_edit_permission_alone_does_not_allow_link_external(): void
    {
        $response = $this->actingAs($this->userWithOnly('parties.edit'))->postJson('/api/parties/resolve-external', [
            'source_system' => 'sales-api',
            'source_type' => 'customer',
            'source_id' => 'ext-2',
            'name' => 'Should be blocked',
        ]);
        $this->assertForbidden($response);
    }
}
