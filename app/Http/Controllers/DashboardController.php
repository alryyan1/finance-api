<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Party;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        // Counts
        $accountsCount = Account::count();
        $partiesCount = Party::where('is_active', true)->count();

        // Posted entries this month
        $entriesThisMonth = JournalEntry::where('is_posted', true)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->count();

        // Total debits through posted entries this month (= total credits = total movement)
        $totalMovement = JournalEntryLine::whereHas('journalEntry', function ($q) use ($monthStart, $monthEnd) {
            $q->where('is_posted', true)->whereBetween('date', [$monthStart, $monthEnd]);
        })->sum('debit');

        // Net profit from posted entries: revenue credits-debits minus expense debits-credits
        $netProfit = $this->calcNetProfit();

        // Recent entries (last 6)
        $recentEntries = JournalEntry::withSum('lines', 'debit')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'date', 'reference', 'description', 'is_posted']);

        // Petty cash funds: balance vs configured thresholds
        $pettyCashFunds = PettyCashFund::where('status', 'active')
            ->get(['id', 'name', 'current_balance', 'max_amount', 'low_balance_threshold'])
            ->map(fn (PettyCashFund $fund) => [
                'id' => $fund->id,
                'name' => $fund->name,
                'current_balance' => $fund->current_balance,
                'max_amount' => $fund->max_amount,
                'low_balance_threshold' => $fund->low_balance_threshold,
                'is_low' => $fund->current_balance <= $fund->low_balance_threshold,
            ]);

        // Petty cash expenses awaiting approval
        $pendingPettyCash = PettyCashTransaction::where('status', 'pending');
        $pendingApprovalsCount = (clone $pendingPettyCash)->count();
        $awaitingAuditorCount = (clone $pendingPettyCash)->whereNull('auditor_approved_at')->count();
        $awaitingManagerCount = (clone $pendingPettyCash)->whereNull('manager_approved_at')->count();

        // Current fiscal year (the one covering today, if any)
        $today = $now->toDateString();
        $fiscalYear = FiscalYear::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first(['id', 'name', 'status', 'start_date', 'end_date']);

        // Revenue/expense trend over the last 6 months (including the current one)
        $trendMonths = 6;
        $trendStart = $now->copy()->subMonths($trendMonths - 1)->startOfMonth();
        $trendEnd = $now->copy()->endOfMonth();
        $monthlyTrend = $this->monthlyTrend($trendStart, $trendEnd, $trendMonths);

        // Top expense accounts over the same trend window
        $topExpenseAccounts = $this->topExpenseAccounts($trendStart->toDateString(), $trendEnd->toDateString());

        return response()->json([
            'accounts_count' => $accountsCount,
            'parties_count' => $partiesCount,
            'entries_this_month' => $entriesThisMonth,
            'total_movement' => $totalMovement,
            'net_profit' => $netProfit,
            'recent_entries' => $recentEntries,
            'petty_cash_funds' => $pettyCashFunds,
            'pending_approvals_count' => $pendingApprovalsCount,
            'awaiting_auditor_count' => $awaitingAuditorCount,
            'awaiting_manager_count' => $awaitingManagerCount,
            'fiscal_year' => $fiscalYear,
            'monthly_trend' => $monthlyTrend,
            'top_expense_accounts' => $topExpenseAccounts,
        ]);
    }

    private function calcNetProfit(): float
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereIn('a.type', ['revenue', 'expense'])
            ->select('a.type', DB::raw('SUM(l.credit) as total_credit'), DB::raw('SUM(l.debit) as total_debit'))
            ->groupBy('a.type')
            ->get()
            ->keyBy('type');

        $revenue = $rows->get('revenue');
        $expense = $rows->get('expense');

        $netRevenue = $revenue ? ($revenue->total_credit - $revenue->total_debit) : 0;
        $netExpense = $expense ? ($expense->total_debit - $expense->total_credit) : 0;

        return $netRevenue - $netExpense;
    }

    /**
     * Monthly revenue/expense/net totals for the given range, keyed to one entry
     * per calendar month (zero-filled) regardless of the database driver — grouped
     * in PHP rather than via a DB-specific date-format function so it behaves the
     * same on sqlite (tests) and mysql (production).
     *
     * @return array<int, array{month: string, revenue: float, expense: float, net: float}>
     */
    private function monthlyTrend(Carbon $start, Carbon $end, int $months): array
    {
        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $buckets[$key] = ['revenue' => 0.0, 'expense' => 0.0];
        }

        $rows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('a.type', ['revenue', 'expense'])
            ->select('e.date', 'a.type', 'l.debit', 'l.credit')
            ->get();

        foreach ($rows as $row) {
            $key = Carbon::parse($row->date)->format('Y-m');
            if (! isset($buckets[$key])) {
                continue;
            }

            if ($row->type === 'revenue') {
                $buckets[$key]['revenue'] += (float) $row->credit - (float) $row->debit;
            } else {
                $buckets[$key]['expense'] += (float) $row->debit - (float) $row->credit;
            }
        }

        return collect($buckets)->map(fn (array $totals, string $month) => [
            'month' => $month,
            'revenue' => round($totals['revenue'], 2),
            'expense' => round($totals['expense'], 2),
            'net' => round($totals['revenue'] - $totals['expense'], 2),
        ])->values()->all();
    }

    /**
     * Top expense accounts by net spend (debit-credit) within the given date range.
     *
     * @return array<int, array{account_id: int, name: string, net_expense: float}>
     */
    private function topExpenseAccounts(string $from, string $to, int $limit = 5): array
    {
        return DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->where('a.type', 'expense')
            ->whereBetween('e.date', [$from, $to])
            ->select('a.id as account_id', 'a.name', DB::raw('SUM(l.debit) - SUM(l.credit) as net_expense'))
            ->groupBy('a.id', 'a.name')
            ->orderByDesc('net_expense')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'name' => $row->name,
                'net_expense' => round((float) $row->net_expense, 2),
            ])
            ->values()
            ->all();
    }
}
