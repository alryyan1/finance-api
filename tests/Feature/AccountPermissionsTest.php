<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Exercises every `permission:accounts.*` middleware gate on the
 * accounts routes (routes/api.php) in isolation: a user with none of the
 * permissions must be denied (403), and a user with only the specific
 * permission under test must be allowed through.
 */
class AccountPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests specifically exercise the permission system — don't
        // let the shared TestCase auto-grant the admin role via actingAs().
        $this->autoGrantAdminRole = false;

        foreach (['accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete'] as $perm) {
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

    private function makeAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'code' => '101',
            'name' => 'Cash',
            'type' => 'asset',
            'is_active' => true,
        ], $overrides));
    }

    // ── accounts.view (index / show) ────────────────────────────────────────

    public function test_index_denied_without_view_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/accounts'));
    }

    public function test_index_allowed_with_view_permission(): void
    {
        $this->actingAs($this->userWithOnly('accounts.view'))
            ->getJson('/api/accounts')->assertOk();
    }

    public function test_show_denied_without_view_permission(): void
    {
        $account = $this->makeAccount();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson("/api/accounts/{$account->id}"));
    }

    public function test_show_allowed_with_view_permission(): void
    {
        $account = $this->makeAccount();
        $this->actingAs($this->userWithOnly('accounts.view'))
            ->getJson("/api/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('id', $account->id);
    }

    // ── accounts.create (store) ─────────────────────────────────────────────

    public function test_store_denied_without_create_permission(): void
    {
        $response = $this->actingAs($this->userWithOnly(null))->postJson('/api/accounts', [
            'code' => '201', 'name' => 'Blocked', 'type' => 'asset',
        ]);
        $this->assertForbidden($response);
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_store_allowed_with_create_permission(): void
    {
        $this->actingAs($this->userWithOnly('accounts.create'))->postJson('/api/accounts', [
            'code' => '201', 'name' => 'Allowed', 'type' => 'asset',
        ])->assertCreated();
        $this->assertDatabaseCount('accounts', 1);
    }

    // ── accounts.edit (update) ──────────────────────────────────────────────

    public function test_update_denied_without_edit_permission(): void
    {
        $account = $this->makeAccount();
        $response = $this->actingAs($this->userWithOnly(null))->putJson("/api/accounts/{$account->id}", [
            'code' => '101', 'name' => 'Blocked update', 'type' => 'asset',
        ]);
        $this->assertForbidden($response);
        $this->assertSame('Cash', $account->fresh()->name);
    }

    public function test_update_allowed_with_edit_permission(): void
    {
        $account = $this->makeAccount();
        $this->actingAs($this->userWithOnly('accounts.edit'))->putJson("/api/accounts/{$account->id}", [
            'code' => '101', 'name' => 'Allowed update', 'type' => 'asset',
        ])->assertOk();
        $this->assertSame('Allowed update', $account->fresh()->name);
    }

    // ── accounts.delete (destroy) ───────────────────────────────────────────

    public function test_destroy_denied_without_delete_permission(): void
    {
        $account = $this->makeAccount();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->deleteJson("/api/accounts/{$account->id}"));
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_destroy_allowed_with_delete_permission(): void
    {
        $account = $this->makeAccount();
        $this->actingAs($this->userWithOnly('accounts.delete'))
            ->deleteJson("/api/accounts/{$account->id}")->assertNoContent();
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    // ── Business rules still apply once permission is granted ──────────────
    // (guards against a fix that grants access but skips existing logic)

    public function test_destroy_still_blocked_by_business_rule_with_delete_permission(): void
    {
        $parent = $this->makeAccount(['code' => '100', 'name' => 'Parent']);
        $this->makeAccount(['code' => '101', 'name' => 'Child', 'parent_id' => $parent->id]);

        $this->actingAs($this->userWithOnly('accounts.delete'))
            ->deleteJson("/api/accounts/{$parent->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $parent->id]);
    }

    public function test_destroy_still_blocked_when_account_has_journal_lines(): void
    {
        $account = $this->makeAccount();
        $other = $this->makeAccount(['code' => '401', 'name' => 'Revenue', 'type' => 'revenue']);
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Entry']);
        $entry->lines()->createMany([
            ['account_id' => $account->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $other->id, 'debit' => 0, 'credit' => 100],
        ]);

        $this->actingAs($this->userWithOnly('accounts.delete'))
            ->deleteJson("/api/accounts/{$account->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    // ── Cross-permission isolation ──────────────────────────────────────────

    public function test_view_permission_alone_does_not_allow_create(): void
    {
        $response = $this->actingAs($this->userWithOnly('accounts.view'))->postJson('/api/accounts', [
            'code' => '301', 'name' => 'Should be blocked', 'type' => 'asset',
        ]);
        $this->assertForbidden($response);
    }

    public function test_create_permission_alone_does_not_allow_delete(): void
    {
        $account = $this->makeAccount();
        $this->assertForbidden($this->actingAs($this->userWithOnly('accounts.create'))->deleteJson("/api/accounts/{$account->id}"));
    }

    public function test_edit_permission_alone_does_not_allow_view(): void
    {
        $account = $this->makeAccount();
        $this->assertForbidden($this->actingAs($this->userWithOnly('accounts.edit'))->getJson("/api/accounts/{$account->id}"));
    }
}
