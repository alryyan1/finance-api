<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cashAccount;

    private Account $capitalAccount;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->cashAccount = Account::create(['code' => '101', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $this->capitalAccount = Account::create(['code' => '301', 'name' => 'Capital', 'type' => 'equity', 'is_active' => true]);

        $this->fiscalYear = FiscalYear::create([
            'name' => 'August 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);
    }

    public function test_rejects_unbalanced_opening_balances(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/opening-balances', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'rows' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->capitalAccount->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('opening_balances', 0);
    }

    public function test_saves_balanced_opening_balances(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/opening-balances', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'rows' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->capitalAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('opening_balances', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->cashAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('opening_balances', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'account_id' => $this->capitalAccount->id,
            'debit' => 0,
            'credit' => 1000,
        ]);
    }
}
