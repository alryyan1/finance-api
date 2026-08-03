<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
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

    private User $manager;

    private User $other;

    private Account $fundAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->other = User::factory()->create();

        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $this->manager->id]);

        $this->fundAccount = Account::create(['code' => '101', 'name' => 'Petty Cash', 'type' => 'asset', 'is_active' => true]);
        $this->expenseAccount = Account::create(['code' => '501', 'name' => 'Office Supplies', 'type' => 'expense', 'is_active' => true]);

        Setting::updateOrCreate(['key' => 'petty_cash_bank_account_id'], ['value' => (string) $this->fundAccount->id]);
    }

    private function createPendingExpense(float $amount = 100): PettyCashTransaction
    {
        return PettyCashTransaction::create([
            'source_account_id' => $this->fundAccount->id,
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
            'source_account_id' => $this->fundAccount->id,
            'description' => 'Stationery',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonPath('journal_entry_id', null);
    }

    public function test_manager_approval_posts_journal_entry(): void
    {
        $txn = $this->createPendingExpense(100);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager");

        $response->assertOk();
        $response->assertJsonPath('status', 'approved');

        $txn->refresh();
        $this->assertNotNull($txn->manager_approved_at);
        $this->assertNotNull($txn->journal_entry_id);

        $entry = JournalEntry::find($txn->journal_entry_id);
        $this->assertSame($this->expenseAccount->id, $entry->lines()->where('debit', '>', 0)->value('account_id'));
        $this->assertSame($this->fundAccount->id, $entry->lines()->where('credit', '>', 0)->value('account_id'));
    }

    public function test_reconcile_endpoint_applies_manager_approval_from_firestore(): void
    {
        // A manager's WhatsApp-tap approval lands in Firestore first — the app
        // must catch MySQL up (and post the journal entry) the next time
        // anyone opens the Petty Cash page, without the manager ever visiting the app.
        $txn = $this->createPendingExpense(100);

        $this->mock(FirestoreApprovalService::class, function ($mock) use ($txn) {
            $mock->shouldReceive('fetch')->with($txn->id)->andReturn(['manager_approved' => true]);
            $mock->shouldReceive('markApproved')->with($txn->id)->once();
        });

        $response = $this->actingAs($this->other)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/reconcile");

        $response->assertOk();
        $response->assertJsonPath('status', 'approved');

        $txn->refresh();
        $this->assertNotNull($txn->manager_approved_at);
        $this->assertNotNull($txn->journal_entry_id);
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
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")
            ->assertForbidden();
    }

    public function test_reapproving_is_idempotent(): void
    {
        $txn = $this->createPendingExpense();

        $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")
            ->assertOk();

        $firstApprovedAt = $txn->fresh()->manager_approved_at;
        $firstJournalEntryId = $txn->fresh()->journal_entry_id;

        $this->actingAs($this->manager)
            ->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")
            ->assertOk();

        $this->assertTrue($firstApprovedAt->equalTo($txn->fresh()->manager_approved_at));
        $this->assertSame($firstJournalEntryId, $txn->fresh()->journal_entry_id);
    }

    public function test_deleting_a_pending_expense_removes_it(): void
    {
        $txn = $this->createPendingExpense(75);

        $this->actingAs($this->other)
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('petty_cash_transactions', ['id' => $txn->id]);
    }

    public function test_deleting_an_approved_expense_deletes_its_journal_entry(): void
    {
        $txn = $this->createPendingExpense(60);

        $this->actingAs($this->manager)->postJson("/api/petty-cash/transactions/{$txn->id}/approve/manager")->assertOk();

        $journalEntryId = $txn->fresh()->journal_entry_id;
        $this->assertNotNull($journalEntryId);

        $this->actingAs($this->other)
            ->deleteJson("/api/petty-cash/transactions/{$txn->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('journal_entries', ['id' => $journalEntryId]);
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
