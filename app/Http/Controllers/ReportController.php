<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function trialBalance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $journalRows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit)  as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->get()
            ->keyBy('account_id');

        $openings = DB::table('opening_balances as ob')
            ->join('accounts as a', 'a.id', '=', 'ob.account_id')
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                'ob.debit as total_debit', 'ob.credit as total_credit')
            ->get()
            ->keyBy('account_id');

        // Merge: all account_ids that appear in either source
        $allIds = $journalRows->keys()->merge($openings->keys())->unique();

        $rows = $allIds->map(function ($id) use ($journalRows, $openings) {
            $j = $journalRows->get($id);
            $o = $openings->get($id);
            $base = $j ?? $o;

            $debit  = (float) ($j->total_debit  ?? 0) + (float) ($o->total_debit  ?? 0);
            $credit = (float) ($j->total_credit ?? 0) + (float) ($o->total_credit ?? 0);
            $balance = abs($debit - $credit);

            return [
                'account_id'   => $base->account_id,
                'code'         => $base->code,
                'name'         => $base->name,
                'type'         => $base->type,
                'total_debit'  => number_format($debit,   2, '.', ''),
                'total_credit' => number_format($credit,  2, '.', ''),
                'balance'      => number_format($balance, 2, '.', ''),
                'balance_side' => $debit >= $credit ? 'debit' : 'credit',
            ];
        })->sortBy('code')->values();

        $totalDebit  = $rows->sum(fn ($r) => (float) $r['total_debit']);
        $totalCredit = $rows->sum(fn ($r) => (float) $r['total_credit']);

        return response()->json([
            'from'   => $from,
            'to'     => $to,
            'rows'   => $rows->values(),
            'totals' => [
                'debit'    => number_format($totalDebit,  2, '.', ''),
                'credit'   => number_format($totalCredit, 2, '.', ''),
                'balanced' => abs($totalDebit - $totalCredit) < 0.005,
            ],
        ]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $accountId = (int) $request->input('account_id');
        $from      = $request->input('from', now()->startOfYear()->toDateString());
        $to        = $request->input('to',   now()->toDateString());

        $account = Account::findOrFail($accountId);

        // Debit-normal: asset, expense → balance increases with debit
        // Credit-normal: liability, equity, revenue → balance increases with credit
        $debitNormal = in_array($account->type, ['asset', 'expense']);

        // Opening balance: stored opening balance + all posted lines before $from
        $storedOb = DB::table('opening_balances')->where('account_id', $accountId)->first();
        $storedObNet = $debitNormal
            ? ((float)($storedOb->debit ?? 0) - (float)($storedOb->credit ?? 0))
            : ((float)($storedOb->credit ?? 0) - (float)($storedOb->debit ?? 0));

        $prePeriod = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', true)
            ->where('e.date', '<', $from)
            ->selectRaw('SUM(l.debit) as d, SUM(l.credit) as c')
            ->first();

        $openingBalance = $storedObNet + ($debitNormal
            ? ((float)($prePeriod->d ?? 0) - (float)($prePeriod->c ?? 0))
            : ((float)($prePeriod->c ?? 0) - (float)($prePeriod->d ?? 0)));

        // Lines within range
        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->leftJoin('parties as p', 'p.id', '=', 'l.party_id')
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->select(
                'e.id as entry_id',
                'e.date',
                'e.reference',
                'e.description as entry_description',
                'l.description as line_description',
                'l.debit',
                'l.credit',
                'p.name as party_name',
            )
            ->orderBy('e.date')
            ->orderBy('e.id')
            ->orderBy('l.id')
            ->get();

        // Compute running balance
        $running = $openingBalance;
        $rows = $lines->map(function ($line) use (&$running, $debitNormal) {
            $debit  = (float) $line->debit;
            $credit = (float) $line->credit;
            $running += $debitNormal ? ($debit - $credit) : ($credit - $debit);

            return [
                'entry_id'         => $line->entry_id,
                'date'             => $line->date,
                'reference'        => $line->reference,
                'entry_description' => $line->entry_description,
                'line_description' => $line->line_description,
                'party_name'       => $line->party_name,
                'debit'            => number_format($debit,  2, '.', ''),
                'credit'           => number_format($credit, 2, '.', ''),
                'balance'          => number_format(abs($running), 2, '.', ''),
                'balance_side'     => $running >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            ];
        });

        $totalDebit  = $lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $lines->sum(fn ($l) => (float) $l->credit);
        $closingBalance = $openingBalance + ($debitNormal
            ? ($totalDebit - $totalCredit)
            : ($totalCredit - $totalDebit));

        return response()->json([
            'account'         => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'from'            => $from,
            'to'              => $to,
            'opening_balance' => number_format(abs($openingBalance), 2, '.', ''),
            'opening_side'    => $openingBalance >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            'closing_balance' => number_format(abs($closingBalance), 2, '.', ''),
            'closing_side'    => $closingBalance >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            'rows'            => $rows->values(),
            'totals'          => [
                'debit'  => number_format($totalDebit,  2, '.', ''),
                'credit' => number_format($totalCredit, 2, '.', ''),
            ],
        ]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = $request->input('as_of', now()->toDateString());

        // Journal balances up to $asOf
        $journalBalances = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->where('e.date', '<=', $asOf)
            ->whereIn('a.type', ['asset', 'liability', 'equity', 'revenue', 'expense'])
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit)  as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->get()
            ->keyBy('account_id');

        // Opening balances
        $openings = DB::table('opening_balances as ob')
            ->join('accounts as a', 'a.id', '=', 'ob.account_id')
            ->whereIn('a.type', ['asset', 'liability', 'equity'])
            ->select('ob.account_id', 'a.code', 'a.name', 'a.type',
                'ob.debit as total_debit', 'ob.credit as total_credit')
            ->get()
            ->keyBy('account_id');

        // Merge both sources
        $allIds = $journalBalances->keys()->merge($openings->keys())->unique();
        $balances = $allIds->map(function ($id) use ($journalBalances, $openings) {
            $j = $journalBalances->get($id);
            $o = $openings->get($id);
            $base = $j ?? $o;
            return (object) [
                'account_id'   => $base->account_id,
                'code'         => $base->code,
                'name'         => $base->name,
                'type'         => $base->type,
                'total_debit'  => (float) ($j->total_debit  ?? 0) + (float) ($o->total_debit  ?? 0),
                'total_credit' => (float) ($j->total_credit ?? 0) + (float) ($o->total_credit ?? 0),
            ];
        });

        $mapRow = function ($row, bool $debitNormal) {
            $net = $debitNormal
                ? ($row->total_debit - $row->total_credit)
                : ($row->total_credit - $row->total_debit);
            return [
                'account_id' => $row->account_id,
                'code'       => $row->code,
                'name'       => $row->name,
                'balance'    => number_format($net, 2, '.', ''),
            ];
        };

        $assets      = $balances->where('type', 'asset')
            ->map(fn ($r) => $mapRow($r, true))->values();
        $liabilities = $balances->where('type', 'liability')
            ->map(fn ($r) => $mapRow($r, false))->values();
        $equity      = $balances->where('type', 'equity')
            ->map(fn ($r) => $mapRow($r, false))->values();

        // Net profit from revenue/expense accounts up to $asOf
        $revenue = $balances->where('type', 'revenue')
            ->sum(fn ($r) => (float)$r->total_credit - (float)$r->total_debit);
        $expense = $balances->where('type', 'expense')
            ->sum(fn ($r) => (float)$r->total_debit - (float)$r->total_credit);
        $netProfit = $revenue - $expense;

        $totalAssets    = $assets->sum(fn ($r) => (float) $r['balance']);
        $totalLiab      = $liabilities->sum(fn ($r) => (float) $r['balance']);
        $totalEquity    = $equity->sum(fn ($r) => (float) $r['balance']);
        $totalEquityNet = $totalEquity + $netProfit;
        $totalLiabEquity = $totalLiab + $totalEquityNet;

        return response()->json([
            'as_of'              => $asOf,
            'assets'             => $assets,
            'liabilities'        => $liabilities,
            'equity'             => $equity,
            'net_profit'         => number_format($netProfit,       2, '.', ''),
            'is_profit'          => $netProfit >= 0,
            'total_assets'       => number_format($totalAssets,     2, '.', ''),
            'total_liabilities'  => number_format($totalLiab,       2, '.', ''),
            'total_equity'       => number_format($totalEquity,     2, '.', ''),
            'total_equity_net'   => number_format($totalEquityNet,  2, '.', ''),
            'total_liab_equity'  => number_format($totalLiabEquity, 2, '.', ''),
            'balanced'           => abs($totalAssets - $totalLiabEquity) < 0.005,
        ]);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $rows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->whereIn('a.type', ['revenue', 'expense'])
            ->select(
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.type',
                DB::raw('SUM(l.debit)  as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.type')
            ->orderBy('a.code')
            ->get()
            ->map(function ($row) {
                $debit  = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                // Revenue: net = credit - debit (credit-normal)
                // Expense: net = debit - credit (debit-normal)
                $net = $row->type === 'revenue'
                    ? ($credit - $debit)
                    : ($debit - $credit);

                return [
                    'account_id'   => $row->account_id,
                    'code'         => $row->code,
                    'name'         => $row->name,
                    'type'         => $row->type,
                    'total_debit'  => number_format($debit,  2, '.', ''),
                    'total_credit' => number_format($credit, 2, '.', ''),
                    'net'          => number_format($net,    2, '.', ''),
                ];
            });

        $totalRevenue = $rows->where('type', 'revenue')->sum(fn ($r) => (float) $r['net']);
        $totalExpense = $rows->where('type', 'expense')->sum(fn ($r) => (float) $r['net']);
        $netProfit    = $totalRevenue - $totalExpense;

        return response()->json([
            'from'          => $from,
            'to'            => $to,
            'revenue'       => $rows->where('type', 'revenue')->values(),
            'expenses'      => $rows->where('type', 'expense')->values(),
            'total_revenue' => number_format($totalRevenue, 2, '.', ''),
            'total_expense' => number_format($totalExpense, 2, '.', ''),
            'net_profit'    => number_format($netProfit,    2, '.', ''),
            'is_profit'     => $netProfit >= 0,
        ]);
    }
}
