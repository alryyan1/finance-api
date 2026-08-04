<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cashAccount;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->cashAccount = Account::create(['code' => '1101', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $this->fiscalYear = FiscalYear::create([
            'name' => 'August 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);
    }

    public function test_ledger_uses_opening_balance_for_the_selected_fiscal_year(): void
    {
        OpeningBalance::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/reports/ledger?'.http_build_query([
            'account_id' => $this->cashAccount->id,
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'fiscal_year_id' => $this->fiscalYear->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('opening_balance', '5000.00');
        $response->assertJsonPath('opening_side', 'debit');
    }

    public function test_ledger_without_fiscal_year_id_ignores_period_scoped_opening_balance(): void
    {
        OpeningBalance::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/reports/ledger?'.http_build_query([
            'account_id' => $this->cashAccount->id,
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertJsonPath('opening_balance', '0.00');
    }

    public function test_ledger_pdf_uses_opening_balance_for_the_selected_fiscal_year(): void
    {
        OpeningBalance::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/api/reports/ledger/pdf?'.http_build_query([
            'account_id' => $this->cashAccount->id,
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'fiscal_year_id' => $this->fiscalYear->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_trial_balance_pdf_uses_opening_balance_for_the_selected_fiscal_year(): void
    {
        OpeningBalance::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/api/reports/trial-balance/pdf?'.http_build_query([
            'fiscal_year_id' => $this->fiscalYear->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_trial_balance_includes_opening_balance_column_and_period_only_totals(): void
    {
        $revenueAccount = Account::create(['code' => '4001', 'name' => 'Sales', 'type' => 'revenue', 'is_active' => true]);

        // General (non-fiscal-year-scoped) opening balance, so it applies regardless of `from`.
        OpeningBalance::create([
            'fiscal_year_id' => null,
            'account_id' => $this->cashAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        // Pre-period activity (before the requested `from`): should be folded into the
        // opening-balance column, not the period totals.
        $prePeriod = JournalEntry::create(['date' => '2026-08-05', 'description' => 'Pre-period sale', 'is_posted' => true]);
        $prePeriod->lines()->createMany([
            ['account_id' => $this->cashAccount->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 1000],
        ]);

        // In-period activity: should appear in total_debit/total_credit only.
        $inPeriod = JournalEntry::create(['date' => '2026-08-20', 'description' => 'In-period sale', 'is_posted' => true]);
        $inPeriod->lines()->createMany([
            ['account_id' => $this->cashAccount->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 200],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/reports/trial-balance?'.http_build_query([
            'from' => '2026-08-15',
            'to' => '2026-08-31',
        ]));

        $response->assertOk();

        $rows = collect($response->json('rows'))->keyBy('code');

        $cash = $rows['1101'];
        $this->assertSame('6000.00', $cash['opening_balance']);
        $this->assertSame('debit', $cash['opening_side']);
        $this->assertSame('200.00', $cash['total_debit']);
        $this->assertSame('0.00', $cash['total_credit']);
        $this->assertSame('6200.00', $cash['balance_debit']);

        $revenue = $rows['4001'];
        $this->assertSame('1000.00', $revenue['opening_balance']);
        $this->assertSame('credit', $revenue['opening_side']);
        $this->assertSame('0.00', $revenue['total_debit']);
        $this->assertSame('200.00', $revenue['total_credit']);
        $this->assertSame('1200.00', $revenue['balance_credit']);

        $response->assertJsonPath('totals.debit', '200.00');
        $response->assertJsonPath('totals.credit', '200.00');
        $response->assertJsonPath('totals.opening_balance', '5000.00');
        $response->assertJsonPath('totals.opening_side', 'debit');
    }

    public function test_balance_sheet_pdf_uses_opening_balance_for_the_selected_fiscal_year(): void
    {
        OpeningBalance::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/api/reports/balance-sheet/pdf?'.http_build_query([
            'fiscal_year_id' => $this->fiscalYear->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
