<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FiscalYearController extends Controller
{
    public function index(): JsonResponse
    {
        $years = FiscalYear::orderByDesc('start_date')->get()->map(function ($y) {
            $unposted = DB::table('journal_entries')
                ->where('is_posted', false)
                ->whereBetween('date', [$y->start_date->toDateString(), $y->end_date->toDateString()])
                ->count();

            $profit = $this->netProfit($y->start_date->toDateString(), $y->end_date->toDateString());

            return array_merge($y->toArray(), [
                'unposted_count' => $unposted,
                'net_profit'     => number_format($profit, 2, '.', ''),
                'is_profit'      => $profit >= 0,
            ]);
        });

        return response()->json($years);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
        ]);

        // Check for date overlap with existing years
        $overlap = FiscalYear::where(function ($q) use ($data) {
            $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
              ->orWhereBetween('end_date',  [$data['start_date'], $data['end_date']])
              ->orWhere(function ($q2) use ($data) {
                  $q2->where('start_date', '<=', $data['start_date'])
                     ->where('end_date',   '>=', $data['end_date']);
              });
        })->exists();

        if ($overlap) {
            return response()->json(['message' => 'تتداخل مع سنة مالية موجودة'], 422);
        }

        return response()->json(FiscalYear::create($data), 201);
    }

    public function close(Request $request, FiscalYear $fiscalYear): JsonResponse
    {
        if ($fiscalYear->status === 'closed') {
            return response()->json(['message' => 'السنة المالية مغلقة مسبقاً'], 422);
        }

        $data = $request->validate([
            'retained_earnings_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $from = $fiscalYear->start_date->toDateString();
        $to   = $fiscalYear->end_date->toDateString();

        // Calculate net balances of revenue/expense accounts for this year
        $rows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->whereIn('a.type', ['revenue', 'expense'])
            ->select('a.id as account_id', 'a.type',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('a.id', 'a.type')
            ->get();

        $lines     = [];
        $netProfit = 0;

        foreach ($rows as $row) {
            $d = (float) $row->total_debit;
            $c = (float) $row->total_credit;

            if ($row->type === 'revenue') {
                $net = $c - $d; // credit-normal; positive = profit
                if (abs($net) > 0.005) {
                    // Debit revenue to zero it out
                    $lines[]    = ['account_id' => $row->account_id, 'debit' => $net, 'credit' => 0, 'description' => 'إقفال إيرادات'];
                    $netProfit += $net;
                }
            } else {
                $net = $d - $c; // debit-normal; positive = expense
                if (abs($net) > 0.005) {
                    // Credit expense to zero it out
                    $lines[]    = ['account_id' => $row->account_id, 'debit' => 0, 'credit' => $net, 'description' => 'إقفال مصروفات'];
                    $netProfit -= $net;
                }
            }
        }

        // Retained earnings balancing line
        if (abs($netProfit) > 0.005) {
            if ($netProfit >= 0) {
                $lines[] = ['account_id' => $data['retained_earnings_account_id'], 'debit' => 0, 'credit' => $netProfit, 'description' => 'صافي الربح'];
            } else {
                $lines[] = ['account_id' => $data['retained_earnings_account_id'], 'debit' => abs($netProfit), 'credit' => 0, 'description' => 'صافي الخسارة'];
            }
        }

        DB::transaction(function () use ($fiscalYear, $lines, $to) {
            $closingEntry = null;

            if (! empty($lines)) {
                $closingEntry = JournalEntry::create([
                    'date'        => $to,
                    'reference'   => 'CLOSE-' . $fiscalYear->id,
                    'description' => 'قيد إقفال السنة المالية: ' . $fiscalYear->name,
                    'is_posted'   => true,
                ]);
                foreach ($lines as $line) {
                    $closingEntry->lines()->create(array_merge($line, ['party_id' => null]));
                }
            }

            $fiscalYear->update([
                'status'           => 'closed',
                'closing_entry_id' => $closingEntry?->id,
                'closed_at'        => now(),
            ]);
        });

        return response()->json($fiscalYear->fresh());
    }

    public function reopen(FiscalYear $fiscalYear): JsonResponse
    {
        if ($fiscalYear->status === 'open') {
            return response()->json(['message' => 'السنة المالية مفتوحة مسبقاً'], 422);
        }

        DB::transaction(function () use ($fiscalYear) {
            if ($fiscalYear->closing_entry_id) {
                $entry = JournalEntry::find($fiscalYear->closing_entry_id);
                if ($entry) {
                    $entry->lines()->delete();
                    $entry->delete();
                }
            }

            $fiscalYear->update([
                'status'           => 'open',
                'closing_entry_id' => null,
                'closed_at'        => null,
            ]);
        });

        return response()->json($fiscalYear->fresh());
    }

    private function netProfit(string $from, string $to): float
    {
        $row = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->whereIn('a.type', ['revenue', 'expense'])
            ->selectRaw("
                SUM(CASE WHEN a.type = 'revenue' THEN l.credit - l.debit ELSE 0 END) as revenue,
                SUM(CASE WHEN a.type = 'expense' THEN l.debit - l.credit ELSE 0 END) as expense
            ")
            ->first();

        return (float) ($row->revenue ?? 0) - (float) ($row->expense ?? 0);
    }
}
