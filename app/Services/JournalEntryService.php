<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Owns the invariants a journal entry must satisfy (balanced lines, fiscal-year
 * lock) so every caller — the JournalEntryController and, later, the sales
 * import command — enforces them the same way.
 */
class JournalEntryService
{
    /**
     * @param  array{date: string, reference?: string|null, description: string, is_posted?: bool, lines: array<int, array{account_id: int, party_id?: int|null, description?: string|null, debit: float, credit: float}>}  $data
     */
    public function create(array $data): JournalEntry
    {
        $this->assertBalanced($data['lines']);
        $this->assertNotLocked($data['date']);

        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::create(Arr::except($data, ['lines']));
            foreach ($data['lines'] as $line) {
                $entry->lines()->create($line);
            }

            return $entry;
        });
    }

    /**
     * @param  array{date: string, reference?: string|null, description: string, is_posted?: bool, lines: array<int, array{account_id: int, party_id?: int|null, description?: string|null, debit: float, credit: float}>}  $data
     */
    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        $this->assertBalanced($data['lines']);
        $this->assertNotLocked($data['date']);

        DB::transaction(function () use ($entry, $data) {
            $entry->update(Arr::except($data, ['lines']));
            $entry->lines()->delete();
            foreach ($data['lines'] as $line) {
                $entry->lines()->create($line);
            }
        });

        return $entry;
    }

    /**
     * @param  array<int, array{debit: float, credit: float}>  $lines
     */
    public function assertBalanced(array $lines): void
    {
        $debit = collect($lines)->sum('debit');
        $credit = collect($lines)->sum('credit');

        if (abs($debit - $credit) > 0.005) {
            abort(422, 'مجموع المدين يجب أن يساوي مجموع الدائن');
        }
    }

    public function assertNotLocked(string $date): void
    {
        if (FiscalYear::isDateLocked($date)) {
            abort(422, 'لا يمكن تعديل قيود في سنة مالية مغلقة');
        }
    }
}
