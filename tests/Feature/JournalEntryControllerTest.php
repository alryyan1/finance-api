<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cashAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->cashAccount = Account::create(['code' => '101', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $this->revenueAccount = Account::create(['code' => '401', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true]);
    }

    private function balancedLines(float $amount = 100): array
    {
        return [
            ['account_id' => $this->cashAccount->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => $amount],
        ];
    }

    public function test_it_creates_a_balanced_journal_entry(): void
    {
        $this->actingAs($this->user)->postJson('/api/journal-entries', [
            'date' => now()->toDateString(),
            'description' => 'Test entry',
            'lines' => $this->balancedLines(),
        ])->assertCreated()
            ->assertJsonPath('description', 'Test entry')
            ->assertJsonCount(2, 'lines');

        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_it_rejects_an_unbalanced_entry(): void
    {
        $this->actingAs($this->user)->postJson('/api/journal-entries', [
            'date' => now()->toDateString(),
            'description' => 'Unbalanced',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 50],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_it_rejects_creation_in_a_closed_fiscal_year(): void
    {
        FiscalYear::create([
            'name' => '2020',
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-31',
            'status' => 'closed',
        ]);

        $this->actingAs($this->user)->postJson('/api/journal-entries', [
            'date' => '2020-06-15',
            'description' => 'Locked period',
            'lines' => $this->balancedLines(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_it_updates_a_draft_entry(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Original', 'is_posted' => false]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->putJson("/api/journal-entries/{$entry->id}", [
            'date' => now()->toDateString(),
            'description' => 'Updated',
            'lines' => $this->balancedLines(200),
        ])->assertOk()
            ->assertJsonPath('description', 'Updated');

        $this->assertSame(200.0, (float) $entry->fresh()->lines->sum('debit'));
    }

    public function test_it_refuses_to_update_a_posted_entry(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Posted', 'is_posted' => true]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->putJson("/api/journal-entries/{$entry->id}", [
            'date' => now()->toDateString(),
            'description' => 'Should fail',
            'lines' => $this->balancedLines(),
        ])->assertStatus(422);
    }

    public function test_it_deletes_a_draft_entry(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'To delete', 'is_posted' => false]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->deleteJson("/api/journal-entries/{$entry->id}")->assertNoContent();

        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    public function test_it_refuses_to_delete_a_posted_entry(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Posted', 'is_posted' => true]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->deleteJson("/api/journal-entries/{$entry->id}")->assertStatus(422);

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    /**
     * Regression test — PATCH .../post previously crashed with
     * "Call to undefined method JournalEntryController::assertNotLocked()"
     * after assertNotLocked() was moved into JournalEntryService but this
     * call site wasn't updated to go through it.
     */
    public function test_it_toggles_the_posted_flag(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Draft', 'is_posted' => false]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->patchJson("/api/journal-entries/{$entry->id}/post")
            ->assertOk()
            ->assertJsonPath('is_posted', true);

        $this->actingAs($this->user)->patchJson("/api/journal-entries/{$entry->id}/post")
            ->assertOk()
            ->assertJsonPath('is_posted', false);
    }

    public function test_it_refuses_to_toggle_posted_in_a_closed_fiscal_year(): void
    {
        $entry = JournalEntry::create(['date' => '2020-06-15', 'description' => 'Old', 'is_posted' => false]);
        $entry->lines()->createMany($this->balancedLines());

        FiscalYear::create([
            'name' => '2020',
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-31',
            'status' => 'closed',
        ]);

        $this->actingAs($this->user)->patchJson("/api/journal-entries/{$entry->id}/post")->assertStatus(422);

        $this->assertFalse($entry->fresh()->is_posted);
    }

    public function test_it_reverses_a_posted_entry(): void
    {
        $party = Party::create(['name' => 'Ahmed', 'type' => 'customer', 'is_active' => true]);
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Posted', 'is_posted' => true]);
        $entry->lines()->create(['account_id' => $this->cashAccount->id, 'party_id' => $party->id, 'debit' => 100, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 100]);

        $response = $this->actingAs($this->user)->postJson("/api/journal-entries/{$entry->id}/reverse")
            ->assertCreated();

        $reversalId = $response->json('id');
        $this->assertSame($entry->id, $response->json('reversal_of'));
        $this->assertSame(100.0, (float) $response->json('lines.0.credit'));
        $this->assertSame(100.0, (float) $response->json('lines.1.debit'));

        $entry->refresh();
        $this->assertSame($reversalId, $entry->reversed_by);
    }

    public function test_it_refuses_to_reverse_a_draft_entry(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Draft', 'is_posted' => false]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->postJson("/api/journal-entries/{$entry->id}/reverse")->assertStatus(422);
    }

    public function test_it_refuses_to_reverse_an_already_reversed_entry(): void
    {
        $entry = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Posted', 'is_posted' => true]);
        $entry->lines()->createMany($this->balancedLines());

        $this->actingAs($this->user)->postJson("/api/journal-entries/{$entry->id}/reverse")->assertCreated();
        $this->actingAs($this->user)->postJson("/api/journal-entries/{$entry->id}/reverse")->assertStatus(422);
    }
}
