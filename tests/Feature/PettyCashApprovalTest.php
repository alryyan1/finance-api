<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\FirestoreApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PettyCashApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    private User $manager;

    private User $other;

    private PettyCashFund $fund;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->other = User::factory()->create();

        Setting::updateOrCreate(['key' => 'petty_cash_auditor_user_id'], ['value' => (string) $this->auditor->id]);
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $this->manager->id]);

        $fundAccount = Account::create(['code' => '101', 'name' => 'Petty Cash', 'type' => 'asset', 'is_active' => true]);
        $this->expenseAccount = Account::create(['code' => '501', 'name' => 'Office Supplies', 'type' => 'expense', 'is_active' => true]);

        $this->fund = PettyCashFund::create([
            'name' => 'Main Fund',
            'custodian_name' => 'Custodian',
            'account_id' => $fundAccount->id,
            'max_amount' => 1000,
            'low_balance_threshold' => 100,
            'current_balance' => 500,
            'status' => 'active',
        ]);
    }

    private function createPendingExpense(float $amount = 100): PettyCashTransaction
    {
        return PettyCashTransaction::create([
            'fund_id' => $this->fund->id,
            'type' => 'expense',
            'status' => 'pending',
            'date' => now()->toDateString(),
            'amount' => $amount,
            'contra_account_id' => $this->expenseAccount->id,
            'description' => 'Test expense',
        ]);
    }

    public function test_creating_an_expense_leaves_it_pending_with_no_accounting_effect(): void
    {
        $response = $this->actingAs($this->other)->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 50,
            'contra_account_id' => $this->expenseAccount->id,
            'description' => 'Stationery',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonPath('journal_entry_id', null);

        $this->assertSame('500.00', $this->fund->fresh()->current_balance);
    }

    public function test_auditor_approval_alone_does_not_post_the_journal_entry(): void
    {
        $txn = $this->createPendingExpense();

        $response = $this->actingAs($this->auditor)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor");

        $response->assertOk();
        $response->assertJsonPath('status', 'pending');

        $txn->refresh();
        $this->assertNotNull($txn->auditor_approved_at);
        $this->assertNull($txn->manager_approved_at);
        $this->assertNull($txn->journal_entry_id);
        $this->assertSame('500.00', $this->fund->fresh()->current_balance);
    }

    public function test_manager_approval_alone_posts_journal_entry_and_deducts_balance(): void
    {
        $txn = $this->createPendingExpense(100);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager");

        $response->assertOk();
        $response->assertJsonPath('status', 'approved');

        $txn->refresh();
        $this->assertNull($txn->auditor_approved_at);
        $this->assertNotNull($txn->manager_approved_at);
        $this->assertNotNull($txn->journal_entry_id);
        $this->assertSame('400.00', $this->fund->fresh()->current_balance);
    }

    public function test_both_approvals_post_journal_entry_and_deduct_balance(): void
    {
        $txn = $this->createPendingExpense(100);

        $this->actingAs($this->auditor)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")
            ->assertOk();

        $response = $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager");

        $response->assertOk();
        $response->assertJsonPath('status', 'approved');

        $txn->refresh();
        $this->assertNotNull($txn->journal_entry_id);
        $this->assertSame('400.00', $this->fund->fresh()->current_balance);
    }

    public function test_auditor_approval_after_manager_does_not_double_post(): void
    {
        $txn = $this->createPendingExpense(100);

        $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")
            ->assertOk();

        $journalEntryId = $txn->fresh()->journal_entry_id;
        $this->assertSame('400.00', $this->fund->fresh()->current_balance);

        $response = $this->actingAs($this->auditor)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor");

        $response->assertOk();

        $txn->refresh();
        $this->assertNotNull($txn->auditor_approved_at);
        $this->assertSame($journalEntryId, $txn->journal_entry_id);
        $this->assertSame('400.00', $this->fund->fresh()->current_balance);
    }

    public function test_reconcile_endpoint_applies_auditor_approval_that_arrives_after_manager(): void
    {
        // Regression test: reconcileFromFirestore() used to bail out once status
        // wasn't "pending" anymore, which the manager's own approval already flips —
        // so an auditor tap landing afterward via WhatsApp never made it into MySQL.
        $txn = $this->createPendingExpense(100);

        $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")
            ->assertOk();

        $txn->refresh();
        $this->assertSame('approved', $txn->status);
        $this->assertNull($txn->auditor_approved_at);

        $this->mock(FirestoreApprovalService::class, function ($mock) use ($txn) {
            $mock->shouldReceive('fetch')->with($txn->id)->andReturn(['auditor_approved' => true, 'manager_approved' => true]);
            $mock->shouldReceive('markApproved')->with($txn->id, 'auditor')->once();
        });

        $response = $this->actingAs($this->other)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile");

        $response->assertOk();
        $response->assertJsonPath('status', 'approved');

        $txn->refresh();
        $this->assertNotNull($txn->auditor_approved_at);
        $this->assertSame('approved', $txn->status);
    }

    public function test_reconcile_endpoint_pulls_down_a_receipt_attached_via_whatsapp(): void
    {
        // The WhatsApp bot uploads straight to Firebase Storage and only touches
        // Firestore — reconcileFromFirestore() must notice the new document_url
        // and download it into local storage so it behaves like an in-app upload.
        Storage::fake('local');
        Http::fake([
            'https://firebasestorage.example/receipt.jpg' => Http::response('fake-image-bytes', 200),
        ]);

        $txn = $this->createPendingExpense(100);
        $this->assertNull($txn->document_path);

        $this->mock(FirestoreApprovalService::class, function ($mock) use ($txn) {
            $mock->shouldReceive('fetch')->with($txn->id)->andReturn([
                'document_url' => 'https://firebasestorage.example/receipt.jpg',
                'document_type' => 'image',
            ]);
        });

        $response = $this->actingAs($this->other)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile");

        $response->assertOk();

        $txn->refresh();
        $this->assertNotNull($txn->document_path);
        $this->assertSame('https://firebasestorage.example/receipt.jpg', $txn->document_firebase_url);
        Storage::disk('local')->assertExists($txn->document_path);
        $this->assertSame('fake-image-bytes', Storage::disk('local')->get($txn->document_path));
    }

    public function test_reconcile_endpoint_is_a_noop_once_document_already_synced(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://firebasestorage.example/receipt.jpg' => Http::response('fake-image-bytes', 200),
        ]);

        $txn = $this->createPendingExpense(100);

        $this->mock(FirestoreApprovalService::class, function ($mock) use ($txn) {
            $mock->shouldReceive('fetch')->with($txn->id)->andReturn([
                'document_url' => 'https://firebasestorage.example/receipt.jpg',
                'document_type' => 'image',
            ]);
        });

        $this->actingAs($this->other)->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile")->assertOk();
        $this->actingAs($this->other)->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile")->assertOk();

        Http::assertSentCount(1);
    }

    public function test_loading_transactions_imports_a_pending_whatsapp_new_expense_request(): void
    {
        $this->mock(FirestoreApprovalService::class, function ($mock) {
            $mock->shouldReceive('fetchPendingWhatsAppRequests')->once()->andReturn([
                [
                    'id' => 'req-1',
                    'amount' => 75.5,
                    'beneficiary_name' => 'Ahmed',
                    'contra_account_id' => (string) $this->expenseAccount->id,
                    'description' => 'Taxi fare',
                    'submitted_by_phone' => '',
                ],
            ]);
            $mock->shouldReceive('createMirror')->once();
            $mock->shouldReceive('deletePendingWhatsAppRequest')->once()->with('req-1');
            $mock->shouldReceive('fetch')->andReturn(null);
        });

        $response = $this->actingAs($this->other)->getJson('/api/petty-cash/transactions');

        $response->assertOk();

        $created = PettyCashTransaction::where('description', 'Taxi fare')->first();
        $this->assertNotNull($created);
        $this->assertSame('pending', $created->status);
        $this->assertSame('75.50', $created->amount);
        $this->assertSame('Ahmed', $created->beneficiary_name);
        $this->assertSame($this->expenseAccount->id, $created->contra_account_id);
    }

    public function test_invalid_whatsapp_new_expense_request_is_dropped_without_creating_a_transaction(): void
    {
        $this->mock(FirestoreApprovalService::class, function ($mock) {
            $mock->shouldReceive('fetchPendingWhatsAppRequests')->once()->andReturn([
                [
                    'id' => 'req-bad',
                    'amount' => 0,
                    'contra_account_id' => (string) $this->expenseAccount->id,
                    'submitted_by_phone' => '',
                ],
            ]);
            $mock->shouldReceive('deletePendingWhatsAppRequest')->once()->with('req-bad');
            $mock->shouldReceive('fetch')->andReturn(null);
        });

        $this->actingAs($this->other)->getJson('/api/petty-cash/transactions')->assertOk();

        $this->assertSame(0, PettyCashTransaction::count());
    }

    public function test_wrong_user_cannot_approve(): void
    {
        $txn = $this->createPendingExpense();

        $this->actingAs($this->other)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")
            ->assertForbidden();
    }

    public function test_reapproving_same_role_is_idempotent(): void
    {
        $txn = $this->createPendingExpense();

        $this->actingAs($this->auditor)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")
            ->assertOk();

        $firstApprovedAt = $txn->fresh()->auditor_approved_at;

        $this->actingAs($this->auditor)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")
            ->assertOk();

        $this->assertTrue($firstApprovedAt->equalTo($txn->fresh()->auditor_approved_at));
    }

    public function test_deleting_a_pending_expense_does_not_touch_the_balance(): void
    {
        $txn = $this->createPendingExpense(75);

        $this->actingAs($this->other)
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}")
            ->assertNoContent();

        $this->assertSame('500.00', $this->fund->fresh()->current_balance);
        $this->assertDatabaseMissing('petty_cash_transactions', ['id' => $txn->id]);
    }

    public function test_deleting_an_approved_expense_reverses_the_balance(): void
    {
        $txn = $this->createPendingExpense(60);

        $this->actingAs($this->auditor)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/auditor")->assertOk();
        $this->actingAs($this->manager)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")->assertOk();

        $this->assertSame('440.00', $this->fund->fresh()->current_balance);

        $this->actingAs($this->other)
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}")
            ->assertNoContent();

        $this->assertSame('500.00', $this->fund->fresh()->current_balance);
    }

    public function test_document_can_be_uploaded_to_an_existing_transaction(): void
    {
        Storage::fake('local');

        $txn = $this->createPendingExpense();

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->other)
            ->post("/api/petty-cash/transactions/{$txn->id}/document", ['document' => $file]);

        $response->assertOk();
        $txn->refresh();
        $this->assertNotNull($txn->document_path);
        $this->assertSame('receipt.pdf', $txn->document_original_name);
        Storage::disk('local')->assertExists($txn->document_path);
    }

    public function test_document_can_be_removed_from_an_existing_transaction(): void
    {
        Storage::fake('local');

        $txn = $this->createPendingExpense();
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        $this->actingAs($this->other)
            ->post("/api/petty-cash/transactions/{$txn->id}/document", ['document' => $file])
            ->assertOk();

        $storedPath = $txn->fresh()->document_path;

        $response = $this->actingAs($this->other)
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}/document");

        $response->assertOk();
        $response->assertJsonPath('document_path', null);

        $txn->refresh();
        $this->assertNull($txn->document_path);
        $this->assertNull($txn->document_original_name);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_removing_document_from_transaction_without_one_returns_404(): void
    {
        $txn = $this->createPendingExpense();

        $this->actingAs($this->other)
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}/document")
            ->assertNotFound();
    }
}
