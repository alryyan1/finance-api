<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use Carbon\Carbon;
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
            'name'        => ['required', 'string', 'max:100'],
            'period_type' => ['required', 'in:yearly,monthly'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after:start_date'],
        ]);

        $overlap = FiscalYear::where(function ($q) use ($data) {
            $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
              ->orWhereBetween('end_date',  [$data['start_date'], $data['end_date']])
              ->orWhere(function ($q2) use ($data) {
                  $q2->where('start_date', '<=', $data['start_date'])
                     ->where('end_date',   '>=', $data['end_date']);
              });
        })->exists();

        if ($overlap) {
            return response()->json(['message' => 'تتداخل مع فترة مالية موجودة'], 422);
        }

        return response()->json(FiscalYear::create($data), 201);
    }

    public function bulkMonths(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = $data['year'];

        $arabicMonths = [
            1 => 'يناير',  2 => 'فبراير', 3 => 'مارس',     4 => 'أبريل',
            5 => 'مايو',   6 => 'يونيو',  7 => 'يوليو',    8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($year, $arabicMonths, &$created, &$skipped) {
            for ($m = 1; $m <= 12; $m++) {
                $start = Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
                $end   = Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

                $overlap = FiscalYear::where('start_date', '<=', $end)
                    ->where('end_date', '>=', $start)
                    ->exists();

                if ($overlap) { $skipped++; continue; }

                FiscalYear::create([
                    'name'        => $arabicMonths[$m] . ' ' . $year,
                    'period_type' => 'monthly',
                    'start_date'  => $start,
                    'end_date'    => $end,
                    'status'      => 'open',
                ]);
                $created++;
            }
        });

        return response()->json(['created' => $created, 'skipped' => $skipped]);
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
                $net = $c - $d;
                if (abs($net) > 0.005) {
                    $lines[]    = ['account_id' => $row->account_id, 'debit' => $net, 'credit' => 0, 'description' => 'إقفال إيرادات'];
                    $netProfit += $net;
                }
            } else {
                $net = $d - $c;
                if (abs($net) > 0.005) {
                    $lines[]    = ['account_id' => $row->account_id, 'debit' => 0, 'credit' => $net, 'description' => 'إقفال مصروفات'];
                    $netProfit -= $net;
                }
            }
        }

        if (abs($netProfit) > 0.005) {
            $lines[] = $netProfit >= 0
                ? ['account_id' => $data['retained_earnings_account_id'], 'debit' => 0, 'credit' => $netProfit, 'description' => 'صافي الربح']
                : ['account_id' => $data['retained_earnings_account_id'], 'debit' => abs($netProfit), 'credit' => 0, 'description' => 'صافي الخسارة'];
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

            // Auto-carry closing balances to the next fiscal year
            $this->carryForwardBalances($fiscalYear);
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
                if ($entry) { $entry->lines()->delete(); $entry->delete(); }
            }

            $fiscalYear->update([
                'status'           => 'open',
                'closing_entry_id' => null,
                'closed_at'        => null,
            ]);
        });

        return response()->json($fiscalYear->fresh());
    }

    /** GET /api/fiscal-years/check-date?date=YYYY-MM-DD */
    public function checkDate(Request $request): JsonResponse
    {
        $request->validate(['date' => ['required', 'date']]);
        $date = $request->input('date');

        $fiscalYear = FiscalYear::where('status', 'open')
            ->where('start_date', '<=', $date)
            ->where('end_date',   '>=', $date)
            ->first(['id', 'name', 'start_date', 'end_date', 'status']);

        return response()->json([
            'covered'     => $fiscalYear !== null,
            'fiscal_year' => $fiscalYear,
        ]);
    }

    /** POST /api/fiscal-years/{fiscal_year}/carry-forward — manual trigger */
    public function carryForwardManual(FiscalYear $fiscalYear): JsonResponse
    {
        if ($fiscalYear->status !== 'closed') {
            return response()->json(['message' => 'يمكن ترحيل الأرصدة من فترة مغلقة فقط'], 422);
        }

        $next = $this->carryForwardBalances($fiscalYear);

        if (! $next) {
            return response()->json(['message' => 'لا توجد فترة مالية تالية لترحيل الأرصدة إليها'], 422);
        }

        return response()->json(['message' => 'تم ترحيل الأرصدة إلى: ' . $next->name]);
    }

    // ─────────────────────────── Private helpers ────────────────────────────

    private function carryForwardBalances(FiscalYear $fiscalYear): ?FiscalYear
    {
        $nextYear = FiscalYear::where('start_date', '>', $fiscalYear->end_date->toDateString())
            ->orderBy('start_date')
            ->first();

        if (! $nextYear) return null;

        $asOf = $fiscalYear->end_date->toDateString();

        // Cumulative journal balances up to end of this fiscal year
        $journalBal = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->where('e.date', '<=', $asOf)
            ->whereIn('a.type', ['asset', 'liability', 'equity'])
            ->select('a.id as account_id', 'a.type',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('a.id', 'a.type')
            ->get()->keyBy('account_id');

        // Opening balances of this fiscal year (to include in carry-forward base)
        $openingBal = OpeningBalance::where('fiscal_year_id', $fiscalYear->id)
            ->get()->keyBy('account_id');

        $allIds = $journalBal->keys()->merge($openingBal->keys())->unique();

        // Replace next year's opening balances entirely
        OpeningBalance::where('fiscal_year_id', $nextYear->id)->delete();

        foreach ($allIds as $accountId) {
            $j = $journalBal->get($accountId);
            $o = $openingBal->get($accountId);

            $totalDebit  = (float) ($j->total_debit  ?? 0) + (float) ($o->debit  ?? 0);
            $totalCredit = (float) ($j->total_credit ?? 0) + (float) ($o->credit ?? 0);
            $type        = $j->type ?? DB::table('accounts')->where('id', $accountId)->value('type');
            $debitNormal = $type === 'asset';

            $net    = $debitNormal ? ($totalDebit - $totalCredit) : ($totalCredit - $totalDebit);
            $debit  = $debitNormal ? max($net, 0)  : max(-$net, 0);
            $credit = $debitNormal ? max(-$net, 0) : max($net,  0);

            if ($debit > 0.005 || $credit > 0.005) {
                OpeningBalance::create([
                    'fiscal_year_id' => $nextYear->id,
                    'account_id'     => $accountId,
                    'debit'          => round($debit, 2),
                    'credit'         => round($credit, 2),
                ]);
            }
        }

        return $nextYear;
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
