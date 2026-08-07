<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBridgeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_and_returns_the_six_sales_bridge_accounts(): void
    {
        $user = User::factory()->create();
        $receivable = Account::create(['code' => '11031', 'name' => 'عملاء', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['code' => '4001', 'name' => 'إيرادات المبيعات', 'type' => 'revenue', 'is_active' => true]);
        $cogs = Account::create(['code' => '5006', 'name' => 'تكلفة المبيعات', 'type' => 'expense', 'is_active' => true]);
        $inventory = Account::create(['code' => '1104', 'name' => 'مخزون البضاعة', 'type' => 'asset', 'is_active' => true]);
        $cash = Account::create(['code' => '1101', 'name' => 'النقدية', 'type' => 'asset', 'is_active' => true]);
        $bank = Account::create(['code' => '1102', 'name' => 'الحساب البنكي', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)->putJson('/api/settings', [
            'sales_receivable_account_id' => $receivable->id,
            'sales_revenue_account_id' => $revenue->id,
            'sales_cogs_account_id' => $cogs->id,
            'sales_inventory_account_id' => $inventory->id,
            'sales_cash_account_id' => $cash->id,
            'sales_bank_account_id' => $bank->id,
        ])->assertOk()
            ->assertJsonPath('sales_receivable_account_id', $receivable->id)
            ->assertJsonPath('sales_revenue_account_id', $revenue->id)
            ->assertJsonPath('sales_cogs_account_id', $cogs->id)
            ->assertJsonPath('sales_inventory_account_id', $inventory->id)
            ->assertJsonPath('sales_cash_account_id', $cash->id)
            ->assertJsonPath('sales_bank_account_id', $bank->id);

        $this->actingAs($user)->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('sales_receivable_account_id', $receivable->id)
            ->assertJsonPath('sales_inventory_account_id', $inventory->id)
            ->assertJsonPath('sales_cash_account_id', $cash->id)
            ->assertJsonPath('sales_bank_account_id', $bank->id);
    }

    public function test_they_default_to_null_when_unset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('sales_receivable_account_id', null)
            ->assertJsonPath('sales_revenue_account_id', null)
            ->assertJsonPath('sales_cogs_account_id', null)
            ->assertJsonPath('sales_inventory_account_id', null)
            ->assertJsonPath('sales_cash_account_id', null)
            ->assertJsonPath('sales_bank_account_id', null);
    }

    public function test_it_rejects_a_nonexistent_account_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/settings', [
            'sales_receivable_account_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sales_receivable_account_id']);
    }
}
