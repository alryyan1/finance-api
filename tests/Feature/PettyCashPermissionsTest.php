<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PettyCashTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Exercises every `permission:petty-cash.*` middleware gate on the
 * petty-cash routes (routes/api.php) in isolation: a user with none of the
 * permissions must be denied (403), and a user with only the specific
 * permission under test must be allowed through.
 */
class PettyCashPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Account $fundAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests specifically exercise the permission system — don't
        // let the shared TestCase auto-grant the admin role via actingAs().
        $this->autoGrantAdminRole = false;

        foreach ([
            'petty-cash.view', 'petty-cash.create', 'petty-cash.edit', 'petty-cash.delete',
            'petty-cash.approve', 'petty-cash.approve.auditor', 'petty-cash.reconcile', 'petty-cash.import-whatsapp',
            'petty-cash.document.upload', 'petty-cash.document.delete', 'petty-cash.export',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        Storage::fake('local');

        $this->fundAccount = Account::create(['code' => '101', 'name' => 'Petty Cash', 'type' => 'asset', 'is_active' => true]);
        $this->expenseAccount = Account::create(['code' => '501', 'name' => 'Office Supplies', 'type' => 'expense', 'is_active' => true]);
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

    private function makeExpense(array $overrides = []): PettyCashTransaction
    {
        return PettyCashTransaction::create(array_merge([
            'source_account_id' => $this->fundAccount->id,
            'type' => 'expense',
            'status' => 'pending',
            'date' => now()->toDateString(),
            'amount' => 100,
            'contra_account_id' => $this->expenseAccount->id,
            'description' => 'Test expense',
        ], $overrides));
    }

    private function expenseWithDocument(): PettyCashTransaction
    {
        $path = UploadedFile::fake()->create('receipt.pdf', 10)->store('petty-cash-receipts', 'local');

        return $this->makeExpense([
            'document_path' => $path,
            'document_original_name' => 'receipt.pdf',
        ]);
    }

    // ── petty-cash.view (transactions list / document) ──────────────────────

    public function test_transactions_list_denied_without_view_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/petty-cash/transactions'));
    }

    public function test_transactions_list_allowed_with_view_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.view'))
            ->getJson('/api/petty-cash/transactions')->assertOk();
    }

    public function test_document_denied_without_view_permission(): void
    {
        $txn = $this->expenseWithDocument();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson("/api/petty-cash/transactions/{$txn->id}/document"));
    }

    public function test_document_allowed_with_view_permission(): void
    {
        $txn = $this->expenseWithDocument();
        $this->actingAs($this->userWithOnly('petty-cash.view'))
            ->getJson("/api/petty-cash/transactions/{$txn->id}/document")->assertOk();
    }

    // ── petty-cash.export (pdf / excel) ─────────────────────────────────────

    public function test_pdf_export_denied_without_export_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/petty-cash/transactions/pdf'));
    }

    public function test_pdf_export_allowed_with_export_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.export'))
            ->getJson('/api/petty-cash/transactions/pdf')->assertOk();
    }

    public function test_excel_export_denied_without_export_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->getJson('/api/petty-cash/transactions/excel'));
    }

    public function test_excel_export_allowed_with_export_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.export'))
            ->getJson('/api/petty-cash/transactions/excel')->assertOk();
    }

    // ── petty-cash.create (expenses / receipts) ─────────────────────────────

    public function test_store_expense_denied_without_create_permission(): void
    {
        $response = $this->actingAs($this->userWithOnly(null))->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 50,
            'contra_account_id' => $this->expenseAccount->id,
            'source_account_id' => $this->fundAccount->id,
        ]);
        $this->assertForbidden($response);
        $this->assertDatabaseCount('petty_cash_transactions', 0);
    }

    public function test_store_expense_allowed_with_create_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.create'))->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 50,
            'contra_account_id' => $this->expenseAccount->id,
            'source_account_id' => $this->fundAccount->id,
        ])->assertCreated();
        $this->assertDatabaseCount('petty_cash_transactions', 1);
    }

    public function test_store_receipt_denied_without_create_permission(): void
    {
        $capital = Account::create(['code' => '301', 'name' => 'Owner Capital', 'type' => 'equity', 'is_active' => true]);
        $response = $this->actingAs($this->userWithOnly(null))->postJson('/api/petty-cash/receipts', [
            'date' => now()->toDateString(),
            'amount' => 200,
            'contra_account_id' => $capital->id,
            'source_account_id' => $this->fundAccount->id,
        ]);
        $this->assertForbidden($response);
        $this->assertDatabaseCount('petty_cash_transactions', 0);
    }

    public function test_store_receipt_allowed_with_create_permission(): void
    {
        $capital = Account::create(['code' => '301', 'name' => 'Owner Capital', 'type' => 'equity', 'is_active' => true]);
        $this->actingAs($this->userWithOnly('petty-cash.create'))->postJson('/api/petty-cash/receipts', [
            'date' => now()->toDateString(),
            'amount' => 200,
            'contra_account_id' => $capital->id,
            'source_account_id' => $this->fundAccount->id,
        ])->assertCreated();
        $this->assertDatabaseCount('petty_cash_transactions', 1);
    }

    // ── petty-cash.delete (destroy) ─────────────────────────────────────────

    public function test_destroy_denied_without_delete_permission(): void
    {
        $txn = $this->makeExpense();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->deleteJson("/api/petty-cash/transactions/{$txn->id}"));
        $this->assertDatabaseHas('petty_cash_transactions', ['id' => $txn->id]);
    }

    public function test_destroy_allowed_with_delete_permission(): void
    {
        $txn = $this->makeExpense();
        $this->actingAs($this->userWithOnly('petty-cash.delete'))
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}")->assertNoContent();
        $this->assertDatabaseMissing('petty_cash_transactions', ['id' => $txn->id]);
    }

    // ── petty-cash.document.upload ──────────────────────────────────────────

    public function test_upload_document_denied_without_permission(): void
    {
        $txn = $this->makeExpense();
        $response = $this->actingAs($this->userWithOnly(null))->postJson("/api/petty-cash/transactions/{$txn->id}/document", [
            'document' => UploadedFile::fake()->create('receipt.pdf', 10),
        ]);
        $this->assertForbidden($response);
        $this->assertNull($txn->fresh()->document_path);
    }

    public function test_upload_document_allowed_with_permission(): void
    {
        $txn = $this->makeExpense();
        $this->actingAs($this->userWithOnly('petty-cash.document.upload'))->postJson("/api/petty-cash/transactions/{$txn->id}/document", [
            'document' => UploadedFile::fake()->create('receipt.pdf', 10),
        ])->assertOk();
        $this->assertNotNull($txn->fresh()->document_path);
    }

    // ── petty-cash.document.delete ──────────────────────────────────────────

    public function test_delete_document_denied_without_permission(): void
    {
        $txn = $this->expenseWithDocument();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->deleteJson("/api/petty-cash/transactions/{$txn->id}/document"));
        $this->assertNotNull($txn->fresh()->document_path);
    }

    public function test_delete_document_allowed_with_permission(): void
    {
        $txn = $this->expenseWithDocument();
        $this->actingAs($this->userWithOnly('petty-cash.document.delete'))
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}/document")->assertOk();
        $this->assertNull($txn->fresh()->document_path);
    }

    // ── petty-cash.approve (manager) ────────────────────────────────────────

    public function test_approve_manager_denied_without_permission(): void
    {
        $manager = $this->userWithOnly(null);
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $manager->id]);
        $txn = $this->makeExpense();

        $this->assertForbidden($this->actingAs($manager)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager"));
        $this->assertNull($txn->fresh()->manager_approved_at);
    }

    public function test_approve_manager_allowed_with_permission(): void
    {
        $manager = $this->userWithOnly('petty-cash.approve');
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $manager->id]);
        $txn = $this->makeExpense();

        $this->actingAs($manager)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")->assertOk();
        $this->assertNotNull($txn->fresh()->manager_approved_at);
    }

    public function test_approve_manager_still_blocked_for_non_designated_manager_even_with_permission(): void
    {
        $designatedManager = User::factory()->create();
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $designatedManager->id]);
        $someoneElse = $this->userWithOnly('petty-cash.approve');
        $txn = $this->makeExpense();

        $this->actingAs($someoneElse)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")
            ->assertStatus(403);
        $this->assertNull($txn->fresh()->manager_approved_at);
    }

    // ── petty-cash.approve.auditor ──────────────────────────────────────────

    public function test_approve_auditor_denied_without_permission(): void
    {
        $auditor = $this->userWithOnly(null);
        Setting::updateOrCreate(['key' => 'petty_cash_auditor_user_id'], ['value' => (string) $auditor->id]);
        $manager = User::factory()->create();
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $manager->id]);
        $txn = $this->makeExpense(['status' => 'approved', 'manager_approved_at' => now(), 'manager_approved_by_user_id' => $manager->id]);

        $this->assertForbidden($this->actingAs($auditor)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor"));
    }

    public function test_approve_auditor_allowed_with_permission(): void
    {
        $auditor = $this->userWithOnly('petty-cash.approve.auditor');
        Setting::updateOrCreate(['key' => 'petty_cash_auditor_user_id'], ['value' => (string) $auditor->id]);
        $manager = User::factory()->create();
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $manager->id]);
        $txn = $this->makeExpense(['status' => 'approved', 'manager_approved_at' => now(), 'manager_approved_by_user_id' => $manager->id]);

        $this->actingAs($auditor)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")->assertOk();
    }

    public function test_auditor_comment_denied_without_permission(): void
    {
        $auditor = $this->userWithOnly(null);
        Setting::updateOrCreate(['key' => 'petty_cash_auditor_user_id'], ['value' => (string) $auditor->id]);
        $txn = $this->makeExpense();

        $this->assertForbidden($this->actingAs($auditor)->postJson("/api/petty-cash/transactions/{$txn->id}/auditor-comment", [
            'auditor_comment' => 'Blocked comment',
        ]));
    }

    public function test_auditor_comment_allowed_with_permission(): void
    {
        $auditor = $this->userWithOnly('petty-cash.approve.auditor');
        Setting::updateOrCreate(['key' => 'petty_cash_auditor_user_id'], ['value' => (string) $auditor->id]);
        $txn = $this->makeExpense();

        $this->actingAs($auditor)->postJson("/api/petty-cash/transactions/{$txn->id}/auditor-comment", [
            'auditor_comment' => 'Looks fine',
        ])->assertOk();
        $this->assertSame('Looks fine', $txn->fresh()->auditor_comment);
    }

    // ── petty-cash.reconcile ────────────────────────────────────────────────

    public function test_reconcile_one_denied_without_permission(): void
    {
        $txn = $this->makeExpense();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile"));
    }

    public function test_reconcile_one_allowed_with_permission(): void
    {
        $txn = $this->makeExpense();
        $this->actingAs($this->userWithOnly('petty-cash.reconcile'))
            ->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile")->assertOk();
    }

    public function test_reconcile_pending_denied_without_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->postJson('/api/petty-cash/reconcile-pending'));
    }

    public function test_reconcile_pending_allowed_with_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.reconcile'))
            ->postJson('/api/petty-cash/reconcile-pending')->assertOk();
    }

    // ── petty-cash.edit (notify / sync-expense-accounts) ────────────────────

    public function test_notify_denied_without_edit_permission(): void
    {
        $txn = $this->makeExpense();
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->postJson("/api/petty-cash/transactions/{$txn->id}/notify"));
    }

    public function test_notify_allowed_with_edit_permission(): void
    {
        $txn = $this->makeExpense();
        $this->actingAs($this->userWithOnly('petty-cash.edit'))
            ->postJson("/api/petty-cash/transactions/{$txn->id}/notify")->assertOk();
    }

    public function test_sync_expense_accounts_denied_without_edit_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->postJson('/api/petty-cash/sync-expense-accounts'));
    }

    public function test_sync_expense_accounts_allowed_with_edit_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.edit'))
            ->postJson('/api/petty-cash/sync-expense-accounts')->assertOk();
    }

    // ── petty-cash.import-whatsapp ──────────────────────────────────────────

    public function test_import_whatsapp_denied_without_permission(): void
    {
        $this->assertForbidden($this->actingAs($this->userWithOnly(null))->postJson('/api/petty-cash/import-whatsapp-requests'));
    }

    public function test_import_whatsapp_allowed_with_permission(): void
    {
        $this->actingAs($this->userWithOnly('petty-cash.import-whatsapp'))
            ->postJson('/api/petty-cash/import-whatsapp-requests')->assertOk();
    }

    // ── Cross-permission isolation ──────────────────────────────────────────

    public function test_view_permission_alone_does_not_allow_create(): void
    {
        $response = $this->actingAs($this->userWithOnly('petty-cash.view'))->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 50,
            'contra_account_id' => $this->expenseAccount->id,
            'source_account_id' => $this->fundAccount->id,
        ]);
        $this->assertForbidden($response);
    }

    public function test_approve_permission_alone_does_not_allow_approve_auditor(): void
    {
        $user = $this->userWithOnly('petty-cash.approve');
        Setting::updateOrCreate(['key' => 'petty_cash_auditor_user_id'], ['value' => (string) $user->id]);
        $txn = $this->makeExpense();

        $this->assertForbidden($this->actingAs($user)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor"));
    }

    public function test_create_permission_alone_does_not_allow_delete(): void
    {
        $txn = $this->makeExpense();
        $this->assertForbidden($this->actingAs($this->userWithOnly('petty-cash.create'))->deleteJson("/api/petty-cash/transactions/{$txn->id}"));
    }
}
