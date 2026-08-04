<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_entries_excel_export_downloads_a_spreadsheet(): void
    {
        $user = User::factory()->create();
        $cash = Account::create(['code' => '1101', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        $capital = Account::create(['code' => '3001', 'name' => 'Capital', 'type' => 'equity', 'is_active' => true]);

        $entry = JournalEntry::create([
            'date' => '2026-08-05',
            'description' => 'Owner contribution',
            'is_posted' => true,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $response = $this->actingAs($user)->get('/api/journal-entries/excel');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_journal_entries_excel_export_respects_filters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/journal-entries/excel?'.http_build_query([
            'from' => '2026-01-01',
            'to' => '2026-12-31',
            'status' => 'posted',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
