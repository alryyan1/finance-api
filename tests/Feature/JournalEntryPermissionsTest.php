<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Exercises every `permission:transactions.*` middleware gate on the
 * journal-entries routes (routes/api.php) in isolation: a user with none
 * of the permissions must be denied (403), and a user with only the
 * specific permission under test must be allowed through.
 */
class JournalEntryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests specifically exercise the permission system — don't
        // let the shared TestCase auto-grant the admin role via actingAs().
        $this->autoGrantAdminRole = false;

        foreach ([
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete',
            'transactions.post', 'transactions.reverse', 'transactions.export',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->cashAccount = Account::create(['code' => '101', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $this->revenueAccount = Account::create(['code' => '401', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true]);
    }

    private function userWithOnly(?string $permission): User
    {
        $user = User::factory()->create();
        if ($permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function balancedLines(float $amount = 100): array
    {
        return [
            ['account_id' => $this->cashAccount->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => $amount],
        ];
    }

    private function makeEntry(bool $posted = false): JournalEntry
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Test entry', 'is_posted' => $posted]);
        $entry->lines()->createMany($this->balancedLines());

        return $entry;
    }

    private function assertForbidden($response): void
    {
        $response->assertStatus(403);
        $response->assertExactJson(['message' => 'ليس لديك الصلاحية اللازمة للقيام بهذا الإجراء.']);
    }

    // ── transactions.view (index / show / voucher) ─────────────────────────

    public function test_index_denied_without_view_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/journal-entries'));
    }

    public function test_index_allowed_with_view_permission(): void
    {
        $this->actingAs($this->userWithOnly('transactions.view'))
            ->getJson('/api/journal-entries')->assertOk();
    }

    public function test_show_denied_without_view_permission(): void
    {
        $entry = $this->makeEntry();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson("/api/journal-entries/{$entry->id}"));
    }

    public function test_show_allowed_with_view_permission(): void
    {
        $entry = $this->makeEntry();
        $this->actingAs($this->userWithOnly('transactions.view'))
            ->getJson("/api/journal-entries/{$entry->id}")->assertOk();
    }

    public function test_voucher_denied_without_view_permission(): void
    {
        $entry = $this->makeEntry();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson("/api/journal-entries/{$entry->id}/voucher"));
    }

    public function test_voucher_allowed_with_view_permission(): void
    {
        $entry = $this->makeEntry();
        $this->actingAs($this->userWithOnly('transactions.view'))
            ->getJson("/api/journal-entries/{$entry->id}/voucher")->assertOk();
    }

    // ── transactions.create (store) ─────────────────────────────────────────

    public function test_store_denied_without_create_permission(): void
    {
        $response = $this->actingAs($this->userWithOnly(null))->postJson('/api/journal-entries', [
            'date' => now()->toDateString(),
            'description' => 'Blocked',
            'lines' => $this->balancedLines(),
        ]);
        $this->assertForbidden($response);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_store_allowed_with_create_permission(): void
    {
        $this->actingAs($this->userWithOnly('transactions.create'))->postJson('/api/journal-entries', [
            'date' => now()->toDateString(),
            'description' => 'Allowed',
            'lines' => $this->balancedLines(),
        ])->assertCreated();
        $this->assertDatabaseCount('journal_entries', 1);
    }

    // ── transactions.edit (update) ──────────────────────────────────────────

    public function test_update_denied_without_edit_permission(): void
    {
        $entry = $this->makeEntry();
        $response = $this->actingAs($this->userWithOnly(null))->putJson("/api/journal-entries/{$entry->id}", [
            'date' => now()->toDateString(),
            'description' => 'Blocked update',
            'lines' => $this->balancedLines(200),
        ]);
        $this->assertForbidden($response);
        $this->assertSame('Test entry', $entry->fresh()->description);
    }

    public function test_update_allowed_with_edit_permission(): void
    {
        $entry = $this->makeEntry();
        $this->actingAs($this->userWithOnly('transactions.edit'))->putJson("/api/journal-entries/{$entry->id}", [
            'date' => now()->toDateString(),
            'description' => 'Allowed update',
            'lines' => $this->balancedLines(200),
        ])->assertOk();
        $this->assertSame('Allowed update', $entry->fresh()->description);
    }

    // ── transactions.delete (destroy) ───────────────────────────────────────

    public function test_destroy_denied_without_delete_permission(): void
    {
        $entry = $this->makeEntry();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->deleteJson("/api/journal-entries/{$entry->id}"));
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    public function test_destroy_allowed_with_delete_permission(): void
    {
        $entry = $this->makeEntry();
        $this->actingAs($this->userWithOnly('transactions.delete'))
            ->deleteJson("/api/journal-entries/{$entry->id}")->assertNoContent();
        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    // ── transactions.post (post) ────────────────────────────────────────────

    public function test_post_denied_without_post_permission(): void
    {
        $entry = $this->makeEntry();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->patchJson("/api/journal-entries/{$entry->id}/post"));
        $this->assertFalse($entry->fresh()->is_posted);
    }

    public function test_post_allowed_with_post_permission(): void
    {
        $entry = $this->makeEntry();
        $this->actingAs($this->userWithOnly('transactions.post'))
            ->patchJson("/api/journal-entries/{$entry->id}/post")->assertOk();
        $this->assertTrue($entry->fresh()->is_posted);
    }

    // ── transactions.reverse (reverse) ──────────────────────────────────────

    public function test_reverse_denied_without_reverse_permission(): void
    {
        $entry = $this->makeEntry(posted: true);
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->postJson("/api/journal-entries/{$entry->id}/reverse"));
        $this->assertNull($entry->fresh()->reversed_by);
    }

    public function test_reverse_allowed_with_reverse_permission(): void
    {
        $entry = $this->makeEntry(posted: true);
        $this->actingAs($this->userWithOnly('transactions.reverse'))
            ->postJson("/api/journal-entries/{$entry->id}/reverse")->assertCreated();
        $this->assertNotNull($entry->fresh()->reversed_by);
    }

    // ── transactions.export (pdf / excel) ───────────────────────────────────

    public function test_pdf_export_denied_without_export_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/journal-entries/pdf'));
    }

    public function test_pdf_export_allowed_with_export_permission(): void
    {
        $this->actingAs($this->userWithOnly('transactions.export'))
            ->getJson('/api/journal-entries/pdf')->assertOk();
    }

    public function test_excel_export_denied_without_export_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/journal-entries/excel'));
    }

    public function test_excel_export_allowed_with_export_permission(): void
    {
        $this->actingAs($this->userWithOnly('transactions.export'))
            ->getJson('/api/journal-entries/excel')->assertOk();
    }

    // ── Cross-permission isolation ──────────────────────────────────────────
    // A permission for one action must not leak into another action.

    public function test_view_permission_alone_does_not_allow_create(): void
    {
        $response = $this->actingAs($this->userWithOnly('transactions.view'))->postJson('/api/journal-entries', [
            'date' => now()->toDateString(),
            'description' => 'Should be blocked',
            'lines' => $this->balancedLines(),
        ]);
        $this->assertForbidden($response);
    }

    public function test_create_permission_alone_does_not_allow_delete(): void
    {
        $entry = $this->makeEntry();
        $this->assertForbidden($this->actingAs($this->userWithOnly('transactions.create'))->deleteJson("/api/journal-entries/{$entry->id}"));
    }

    public function test_post_permission_alone_does_not_allow_reverse(): void
    {
        $entry = $this->makeEntry(posted: true);
        $this->assertForbidden($this->actingAs($this->userWithOnly('transactions.post'))->postJson("/api/journal-entries/{$entry->id}/reverse"));
    }
}
