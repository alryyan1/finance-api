<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetHorizontalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cashAccount;

    private Account $equityAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->cashAccount = Account::create(['code' => '101', 'name' => 'Cash', 'type' => 'asset', 'sub_type' => 'current', 'is_active' => true]);
        $this->equityAccount = Account::create(['code' => '301', 'name' => 'Owner Capital', 'type' => 'equity', 'is_active' => true]);
    }

    private function postJournalEntry(string $date, float $amount): void
    {
        $entry = JournalEntry::create(['date' => $date, 'description' => 'Capital injection', 'is_posted' => true]);
        $entry->lines()->createMany([
            ['account_id' => $this->cashAccount->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->equityAccount->id, 'debit' => 0, 'credit' => $amount],
        ]);
    }

    public function test_horizontal_analysis_computes_diff_and_percent_between_two_dates(): void
    {
        $this->postJournalEntry('2025-01-01', 1000);
        $this->postJournalEntry('2026-01-01', 500);

        $response = $this->actingAs($this->user)->getJson('/api/reports/balance-sheet/horizontal?'.http_build_query([
            'from_as_of' => '2025-06-30',
            'to_as_of' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertJsonPath('from_as_of', '2025-06-30');
        $response->assertJsonPath('to_as_of', '2026-06-30');

        $cashRow = collect($response->json('current_assets'))->firstWhere('account_id', $this->cashAccount->id);
        $this->assertSame('1000.00', $cashRow['from']);
        $this->assertSame('1500.00', $cashRow['to']);
        $this->assertSame('500.00', $cashRow['diff']);
        $this->assertEquals(50.0, $cashRow['percent']);

        $response->assertJsonPath('totals.total_assets.from', '1000.00');
        $response->assertJsonPath('totals.total_assets.to', '1500.00');
        $response->assertJsonPath('totals.total_assets.diff', '500.00');
        $response->assertJsonPath('totals.total_assets.percent', 50);
    }

    public function test_horizontal_analysis_returns_null_percent_when_the_earlier_balance_is_zero(): void
    {
        $this->postJournalEntry('2026-01-01', 500);

        $response = $this->actingAs($this->user)->getJson('/api/reports/balance-sheet/horizontal?'.http_build_query([
            'from_as_of' => '2025-12-31',
            'to_as_of' => '2026-06-30',
        ]));

        $response->assertOk();
        $cashRow = collect($response->json('current_assets'))->firstWhere('account_id', $this->cashAccount->id);
        $this->assertSame('0.00', $cashRow['from']);
        $this->assertSame('500.00', $cashRow['to']);
        $this->assertNull($cashRow['percent']);
    }

    public function test_horizontal_analysis_pdf_renders(): void
    {
        $this->postJournalEntry('2025-01-01', 1000);

        $response = $this->actingAs($this->user)->get('/api/reports/balance-sheet/horizontal/pdf?'.http_build_query([
            'from_as_of' => '2024-12-31',
            'to_as_of' => '2025-12-31',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
