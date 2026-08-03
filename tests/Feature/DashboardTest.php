<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_petty_cash_fiscal_year_and_approval_data(): void
    {
        $user = User::factory()->create();

        $fundAccount = Account::create(['code' => '101', 'name' => 'Petty Cash', 'type' => 'asset', 'is_active' => true]);
        $expenseAccount = Account::create(['code' => '501', 'name' => 'Office Supplies', 'type' => 'expense', 'is_active' => true]);

        $lowFund = PettyCashFund::create([
            'name' => 'Low Fund',
            'custodian_name' => 'Custodian',
            'account_id' => $fundAccount->id,
            'max_amount' => 1000,
            'low_balance_threshold' => 100,
            'current_balance' => 50,
            'status' => 'active',
        ]);

        PettyCashTransaction::create([
            'fund_id' => $lowFund->id,
            'type' => 'expense',
            'status' => 'pending',
            'date' => now()->toDateString(),
            'amount' => 25,
            'beneficiary_name' => 'Vendor',
            'contra_account_id' => $expenseAccount->id,
            'description' => 'Test expense',
        ]);

        FiscalYear::create([
            'name' => 'FY '.now()->year,
            'period_type' => 'yearly',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('petty_cash_funds.0.name', 'Low Fund')
            ->assertJsonPath('petty_cash_funds.0.is_low', true)
            ->assertJsonPath('pending_approvals_count', 1)
            ->assertJsonPath('awaiting_auditor_count', 1)
            ->assertJsonPath('awaiting_manager_count', 1)
            ->assertJsonPath('fiscal_year.status', 'open');
    }

    public function test_dashboard_reports_monthly_trend_and_top_expense_accounts(): void
    {
        $user = User::factory()->create();

        $cashAccount = Account::create(['code' => '100', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $revenueAccount = Account::create(['code' => '400', 'name' => 'Sales', 'type' => 'revenue', 'is_active' => true]);
        $rentAccount = Account::create(['code' => '502', 'name' => 'Rent', 'type' => 'expense', 'is_active' => true]);
        $suppliesAccount = Account::create(['code' => '503', 'name' => 'Supplies', 'type' => 'expense', 'is_active' => true]);

        // Revenue entry this month: debit cash 500, credit revenue 500
        $sale = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Sale', 'is_posted' => true]);
        $sale->lines()->createMany([
            ['account_id' => $cashAccount->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 500],
        ]);

        // Rent expense this month: debit rent 300, credit cash 300
        $rent = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Rent', 'is_posted' => true]);
        $rent->lines()->createMany([
            ['account_id' => $rentAccount->id, 'debit' => 300, 'credit' => 0],
            ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 300],
        ]);

        // Supplies expense this month: debit supplies 100, credit cash 100
        $supplies = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Supplies', 'is_posted' => true]);
        $supplies->lines()->createMany([
            ['account_id' => $suppliesAccount->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 100],
        ]);

        // Unposted entry should be excluded entirely
        $draft = JournalEntry::create(['date' => now()->toDateString(), 'description' => 'Draft', 'is_posted' => false]);
        $draft->lines()->createMany([
            ['account_id' => $rentAccount->id, 'debit' => 999, 'credit' => 0],
            ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 999],
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');
        $response->assertOk();

        $currentMonth = now()->format('Y-m');
        $trend = collect($response->json('monthly_trend'));
        $this->assertCount(6, $trend);
        $currentBucket = $trend->firstWhere('month', $currentMonth);
        $this->assertNotNull($currentBucket);
        $this->assertEquals(500, $currentBucket['revenue']);
        $this->assertEquals(400, $currentBucket['expense']);
        $this->assertEquals(100, $currentBucket['net']);

        $topExpenses = $response->json('top_expense_accounts');
        $this->assertSame('Rent', $topExpenses[0]['name']);
        $this->assertEquals(300, $topExpenses[0]['net_expense']);
        $this->assertSame('Supplies', $topExpenses[1]['name']);
        $this->assertEquals(100, $topExpenses[1]['net_expense']);
    }
}
