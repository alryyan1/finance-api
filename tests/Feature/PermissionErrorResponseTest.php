<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests specifically exercise permission-denial — don't let the
        // shared TestCase auto-grant the admin role via actingAs().
        $this->autoGrantAdminRole = false;
    }

    public function test_a_user_without_the_post_permission_gets_a_clean_403_when_posting_a_journal_entry(): void
    {
        $debit = Account::create(['code' => '101', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $credit = Account::create(['code' => '401', 'name' => 'Revenue', 'type' => 'revenue', 'is_active' => true]);

        $entry = JournalEntry::create([
            'date' => now()->toDateString(),
            'description' => 'Test entry',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $debit->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $credit->id, 'debit' => 0, 'credit' => 100],
        ]);

        $user = User::factory()->create(); // no roles/permissions at all

        $response = $this->actingAs($user)->patchJson("/api/journal-entries/{$entry->id}/post");

        $response->assertStatus(403);
        $response->assertExactJson([
            'message' => 'ليس لديك الصلاحية اللازمة للقيام بهذا الإجراء.',
        ]);
    }
}
