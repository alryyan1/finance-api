<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PettyCashWhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private int $fundAccountId;

    private function setUpFund(): Account
    {
        $fundAccount = Account::create(['code' => '101', 'name' => 'Petty Cash', 'type' => 'asset', 'is_active' => true]);
        $expenseAccount = Account::create(['code' => '501', 'name' => 'Office Supplies', 'type' => 'expense', 'is_active' => true]);

        Setting::updateOrCreate(['key' => 'petty_cash_bank_account_id'], ['value' => (string) $fundAccount->id]);
        $this->fundAccountId = $fundAccount->id;

        return $expenseAccount;
    }

    public function test_creating_an_expense_sends_a_whatsapp_template_to_the_manager(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.manager_template' => 'petty_cash_manager_approval',
            'services.whatsapp.template_language' => 'ar',
        ]);

        Setting::updateOrCreate(['key' => 'petty_cash_manager_whatsapp_phone'], ['value' => '+249900000001']);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $expenseAccount = $this->setUpFund();
        $user = User::factory()->create(['name' => 'Ahmed Requester']);

        $response = $this->actingAs($user)->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 42.5,
            'contra_account_id' => $expenseAccount->id,
            'source_account_id' => $this->fundAccountId,
            'description' => 'Stationery',
        ])->assertCreated();

        $response->assertJsonPath('notifications.0.role', 'manager');
        $response->assertJsonPath('notifications.0.status', 'sent');

        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            $params = $request['template']['components'][0]['parameters'];
            $approveButton = $request['template']['components'][1];
            $documentButton = $request['template']['components'][2];

            return str_contains($request->url(), 'graph.facebook.com/v20.0/1234567890/messages')
                && $request['messaging_product'] === 'whatsapp'
                && $request['type'] === 'template'
                && $request['template']['language']['code'] === 'ar'
                && count($params) === 5
                && $params[0]['text'] === '42.50'
                && $params[1]['text'] === 'Stationery'
                && $params[2]['text'] === 'Ahmed Requester'
                && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $params[3]['text']) === 1
                && $params[4]['text'] === '—'
                && $approveButton['index'] === '0'
                && preg_match('/^petty_cash_approvals:[^:]+:\d+:manager$/', $approveButton['parameters'][0]['payload']) === 1
                && $documentButton['index'] === '1'
                && preg_match('/^petty_cash_documents:[^:]+:\d+:manager:(image|document|none)$/', $documentButton['parameters'][0]['payload']) === 1;
        });

        Http::assertSent(fn ($request) => $request['to'] === '+249900000001' && $request['template']['name'] === 'petty_cash_manager_approval');
    }

    public function test_no_whatsapp_request_sent_when_phone_or_template_missing(): void
    {
        Http::fake();

        $expenseAccount = $this->setUpFund();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 10,
            'contra_account_id' => $expenseAccount->id,
            'source_account_id' => $this->fundAccountId,
        ])->assertCreated();

        $response->assertJsonPath('notifications.0.status', 'skipped');

        Http::assertNothingSent();
    }

    public function test_resend_notification_endpoint_sends_again(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.manager_template' => 'petty_cash_manager_approval',
        ]);

        Setting::updateOrCreate(['key' => 'petty_cash_manager_whatsapp_phone'], ['value' => '+249900000001']);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $expenseAccount = $this->setUpFund();
        $user = User::factory()->create();

        $created = $this->actingAs($user)->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 10,
            'contra_account_id' => $expenseAccount->id,
            'source_account_id' => $this->fundAccountId,
        ])->json();

        Http::assertSentCount(1);

        $response = $this->actingAs($user)
            ->postJson("/api/petty-cash/transactions/{$created['id']}/notify");

        $response->assertOk();
        $response->assertJsonPath('notifications.0.status', 'sent');

        Http::assertSentCount(2);
    }

    public function test_notify_on_create_disabled_skips_sending_on_creation(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.manager_template' => 'petty_cash_manager_approval',
        ]);

        Setting::updateOrCreate(['key' => 'petty_cash_manager_whatsapp_phone'], ['value' => '+249900000001']);
        Setting::updateOrCreate(['key' => 'petty_cash_notify_on_create'], ['value' => '0']);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $expenseAccount = $this->setUpFund();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 10,
            'contra_account_id' => $expenseAccount->id,
            'source_account_id' => $this->fundAccountId,
        ])->assertCreated();

        $response->assertJsonPath('notifications', []);
        Http::assertNothingSent();
    }

    public function test_resend_notification_endpoint_rejects_already_approved_expense(): void
    {
        $manager = User::factory()->create();
        Setting::updateOrCreate(['key' => 'petty_cash_manager_user_id'], ['value' => (string) $manager->id]);

        Http::fake();

        $expenseAccount = $this->setUpFund();
        $user = User::factory()->create();

        $created = $this->actingAs($user)->postJson('/api/petty-cash/expenses', [
            'date' => now()->toDateString(),
            'amount' => 10,
            'contra_account_id' => $expenseAccount->id,
            'source_account_id' => $this->fundAccountId,
        ])->json();

        $this->actingAs($manager)->postJson("/api/petty-cash/transactions/{$created['id']}/approve/manager")->assertOk();

        $this->actingAs($user)
            ->postJson("/api/petty-cash/transactions/{$created['id']}/notify")
            ->assertStatus(422);
    }
}
