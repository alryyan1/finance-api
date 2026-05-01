<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,posted,draft'],
        ]);

        $q = JournalEntry::withSum('lines', 'debit')
            ->withCount('lines')
            ->with('lines.account:id,code,name')
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($from = $request->input('from')) {
            $q->where('date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $q->where('date', '<=', $to);
        }
        if ($search = $request->input('search')) {
            $q->where(function ($sub) use ($search) {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            if ($status === 'posted') $q->where('is_posted', true);
            if ($status === 'draft')  $q->where('is_posted', false);
        }

        return response()->json($q->get());
    }

    public function show(JournalEntry $journalEntry): JsonResponse
    {
        return response()->json(
            $journalEntry->load('lines.account:id,code,name', 'lines.party:id,name')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'                => ['required', 'date'],
            'reference'           => ['nullable', 'string', 'max:50'],
            'description'         => ['required', 'string', 'max:500'],
            'lines'               => ['required', 'array', 'min:2'],
            'lines.*.account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.party_id'    => ['nullable', 'integer', 'exists:parties,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit'       => ['required', 'numeric', 'min:0'],
            'lines.*.credit'      => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertBalanced($data['lines']);

        $entry = DB::transaction(function () use ($data) {
            $entry = JournalEntry::create(Arr::except($data, ['lines']));
            foreach ($data['lines'] as $line) {
                $entry->lines()->create($line);
            }
            return $entry;
        });

        return response()->json(
            $entry->load('lines.account:id,code,name', 'lines.party:id,name'),
            201
        );
    }

    public function update(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        if ($journalEntry->is_posted) {
            return response()->json(['message' => 'لا يمكن تعديل قيد مرحَّل'], 422);
        }

        $data = $request->validate([
            'date'                => ['required', 'date'],
            'reference'           => ['nullable', 'string', 'max:50'],
            'description'         => ['required', 'string', 'max:500'],
            'lines'               => ['required', 'array', 'min:2'],
            'lines.*.account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.party_id'    => ['nullable', 'integer', 'exists:parties,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit'       => ['required', 'numeric', 'min:0'],
            'lines.*.credit'      => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertBalanced($data['lines']);

        DB::transaction(function () use ($journalEntry, $data) {
            $journalEntry->update(Arr::except($data, ['lines']));
            $journalEntry->lines()->delete();
            foreach ($data['lines'] as $line) {
                $journalEntry->lines()->create($line);
            }
        });

        return response()->json(
            $journalEntry->load('lines.account:id,code,name', 'lines.party:id,name')
        );
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        if ($journalEntry->is_posted) {
            return response()->json(['message' => 'لا يمكن حذف قيد مرحَّل'], 422);
        }

        $journalEntry->lines()->delete();
        $journalEntry->delete();

        return response()->json(null, 204);
    }

    public function post(JournalEntry $journalEntry): JsonResponse
    {
        $journalEntry->update(['is_posted' => ! $journalEntry->is_posted]);

        return response()->json($journalEntry->fresh());
    }

    private function assertBalanced(array $lines): void
    {
        $debit  = collect($lines)->sum('debit');
        $credit = collect($lines)->sum('credit');

        if (abs($debit - $credit) > 0.005) {
            abort(422, 'مجموع المدين يجب أن يساوي مجموع الدائن');
        }
    }
}
