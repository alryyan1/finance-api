<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now          = now();
        $monthStart   = $now->copy()->startOfMonth()->toDateString();
        $monthEnd     = $now->copy()->endOfMonth()->toDateString();

        // Counts
        $accountsCount = Account::count();
        $partiesCount  = Party::where('is_active', true)->count();

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

        return response()->json([
            'accounts_count'     => $accountsCount,
            'parties_count'      => $partiesCount,
            'entries_this_month' => $entriesThisMonth,
            'total_movement'     => $totalMovement,
            'net_profit'         => $netProfit,
            'recent_entries'     => $recentEntries,
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

        $netRevenue  = $revenue ? ($revenue->total_credit - $revenue->total_debit) : 0;
        $netExpense  = $expense ? ($expense->total_debit  - $expense->total_credit) : 0;

        return $netRevenue - $netExpense;
    }
}
