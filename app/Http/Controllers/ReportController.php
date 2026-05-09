<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\PdfReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // ────────────────────────────── JSON endpoints ──────────────────────────────

    public function trialBalance(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId] = $this->validateDateRange($request);
        return response()->json($this->trialBalanceData($from, $to, $fyId));
    }

    public function ledger(Request $request): JsonResponse
    {
        $request->validate([
            'account_id'     => ['required', 'integer', 'exists:accounts,id'],
            'party_id'       => ['nullable', 'integer', 'exists:parties,id'],
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        [$from, $to] = $this->resolveDates($request, now()->startOfYear()->toDateString(), now()->toDateString());
        return response()->json($this->ledgerData(
            (int) $request->input('account_id'),
            $from,
            $to,
            $request->input('party_id') ? (int) $request->input('party_id') : null
        ));
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $this->validateDateRange($request);
        return response()->json($this->incomeStatementData($from, $to));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'as_of'          => ['nullable', 'date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy   = \App\Models\FiscalYear::findOrFail($fyId);
            $asOf = $fy->end_date->toDateString();
        } else {
            $asOf = $request->input('as_of', now()->toDateString());
        }
        return response()->json($this->balanceSheetData($asOf, $fyId));
    }

    // ────────────────────────────── PDF endpoints ───────────────────────────────

    public function trialBalancePdf(Request $request): Response
    {
        ['from' => $from, 'to' => $to] = $this->validateDateRange($request);
        $viewType = $request->input('view_type', 'both'); // totals | balances | both
        $data = $this->trialBalanceData($from, $to);

        $subtitles = [
            'totals'   => 'بالمجاميع',
            'balances' => 'بالأرصدة',
            'both'     => 'بالمجاميع والأرصدة',
        ];
        $subtitle = "الفترة من {$from} إلى {$to} — " . ($subtitles[$viewType] ?? $subtitles['both']);
        $pdf = PdfReport::make('ميزان المراجعة', $subtitle);

        $typeLabels = ['asset' => 'أصول', 'liability' => 'خصوم', 'equity' => 'حقوق الملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات'];
        $typeOrder  = ['asset', 'liability', 'equity', 'revenue', 'expense'];

        if ($viewType === 'totals') {
            $cols = [20, 112, 29, 29];
            $pdf->tableHead(['الرمز', 'اسم الحساب', 'مجموع مدين', 'مجموع دائن'], $cols);
        } elseif ($viewType === 'balances') {
            $cols = [20, 112, 29, 29];
            $pdf->tableHead(['الرمز', 'اسم الحساب', 'رصيد مدين', 'رصيد دائن'], $cols);
        } else {
            $cols = [20, 68, 25, 25, 26, 26];
            $pdf->tableHead(['الرمز', 'اسم الحساب', 'مجموع مدين', 'مجموع دائن', 'رصيد مدين', 'رصيد دائن'], $cols);
        }

        $odd = false;
        foreach ($typeOrder as $type) {
            $group = collect($data['rows'])->where('type', $type)->values();
            if ($group->isEmpty()) continue;
            $pdf->sectionHead($typeLabels[$type]);

            foreach ($group as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['code'], 1, 0, 'C', true);
                $pdf->Cell($cols[1], 7, $row['name'], 1, 0, 'R', true);
                if ($viewType === 'totals') {
                    $pdf->Cell($cols[2], 7, PdfReport::n($row['total_debit']),  1, 0, 'C', true);
                    $pdf->Cell($cols[3], 7, PdfReport::n($row['total_credit']), 1, 1, 'C', true);
                } elseif ($viewType === 'balances') {
                    $pdf->Cell($cols[2], 7, PdfReport::n($row['balance_debit']),  1, 0, 'C', true);
                    $pdf->Cell($cols[3], 7, PdfReport::n($row['balance_credit']), 1, 1, 'C', true);
                } else {
                    $pdf->Cell($cols[2], 7, PdfReport::n($row['total_debit']),    1, 0, 'C', true);
                    $pdf->Cell($cols[3], 7, PdfReport::n($row['total_credit']),   1, 0, 'C', true);
                    $pdf->Cell($cols[4], 7, PdfReport::n($row['balance_debit']),  1, 0, 'C', true);
                    $pdf->Cell($cols[5], 7, PdfReport::n($row['balance_credit']), 1, 1, 'C', true);
                }
                $odd = !$odd;
            }
        }

        $t = $data['totals'];
        if ($viewType === 'totals') {
            $pdf->totalsRow(
                ['الإجمالي', PdfReport::n($t['debit']), PdfReport::n($t['credit'])],
                [$cols[0] + $cols[1], $cols[2], $cols[3]]
            );
        } elseif ($viewType === 'balances') {
            $pdf->totalsRow(
                ['الإجمالي', PdfReport::n($t['balance_debit']), PdfReport::n($t['balance_credit'])],
                [$cols[0] + $cols[1], $cols[2], $cols[3]]
            );
        } else {
            $pdf->totalsRow(
                ['الإجمالي', PdfReport::n($t['debit']), PdfReport::n($t['credit']), PdfReport::n($t['balance_debit']), PdfReport::n($t['balance_credit'])],
                [$cols[0] + $cols[1], $cols[2], $cols[3], $cols[4], $cols[5]]
            );
        }

        return $pdf->respond('trial-balance.pdf');
    }

    public function ledgerPdf(Request $request): Response
    {
        $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'party_id'   => ['nullable', 'integer', 'exists:parties,id'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to',   now()->toDateString());
        $data = $this->ledgerData(
            (int) $request->input('account_id'),
            $from,
            $to,
            $request->input('party_id') ? (int) $request->input('party_id') : null
        );

        $acct = $data['account'];
        $pdf  = PdfReport::make(
            'كشف حساب: ' . $acct['name'],
            "من {$from} إلى {$to}"
        );

        $cols = [22, 20, 60, 28, 20, 20, 20];
        $pdf->tableHead(['التاريخ', 'مرجع', 'البيان', 'الطرف', 'مدين', 'دائن', 'الرصيد'], $cols);

        // Opening balance row
        $obSide = $data['opening_side'] === 'debit' ? ' م' : ' د';
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetFont('dejavusans', 'I', 8);
        $pdf->Cell($cols[0], 6, $from,                                              1, 0, 'C', true);
        $pdf->Cell($cols[1], 6, '',                                                 1, 0, 'C', true);
        $pdf->Cell($cols[2], 6, 'رصيد افتتاحي',                                    1, 0, 'R', true);
        $pdf->Cell($cols[3], 6, '',                                                 1, 0, 'C', true);
        $pdf->Cell($cols[4], 6, '',                                                 1, 0, 'C', true);
        $pdf->Cell($cols[5], 6, '',                                                 1, 0, 'C', true);
        $pdf->Cell($cols[6], 6, PdfReport::n($data['opening_balance']) . $obSide,  1, 1, 'C', true);
        $pdf->SetFont('dejavusans', '', 9);

        $odd = false;
        foreach ($data['rows'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $side    = $row['balance_side'] === 'debit' ? ' م' : ' د';
            $debit   = (float) $row['debit']  > 0 ? PdfReport::n($row['debit'])  : '—';
            $credit  = (float) $row['credit'] > 0 ? PdfReport::n($row['credit']) : '—';
            $desc    = $row['entry_description'];

            $pdf->Cell($cols[0], 7, $row['date'],                              1, 0, 'C', true);
            $pdf->Cell($cols[1], 7, $row['reference'] ?? '—',                  1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, $desc,                                     1, 0, 'R', true);
            $pdf->Cell($cols[3], 7, $row['party_name'] ?? '—',                 1, 0, 'R', true);
            $pdf->Cell($cols[4], 7, $debit,                                    1, 0, 'C', true);
            $pdf->Cell($cols[5], 7, $credit,                                   1, 0, 'C', true);
            $pdf->Cell($cols[6], 7, PdfReport::n($row['balance']) . $side,     1, 1, 'C', true);
            $odd = !$odd;
        }

        $clSide = $data['closing_side'] === 'debit' ? ' م' : ' د';
        $pdf->totalsRow(
            ['الإجمالي', PdfReport::n($data['totals']['debit']), PdfReport::n($data['totals']['credit']), PdfReport::n($data['closing_balance']) . $clSide],
            [$cols[0] + $cols[1] + $cols[2] + $cols[3], $cols[4], $cols[5], $cols[6]]
        );

        return $pdf->respond('ledger.pdf');
    }

    public function incomeStatementPdf(Request $request): Response
    {
        ['from' => $from, 'to' => $to] = $this->validateDateRange($request);
        $data = $this->incomeStatementData($from, $to);

        $pdf  = PdfReport::make('قائمة الدخل', "الفترة من {$from} إلى {$to}");
        $cols = [20, 140, 30];

        // Revenue section
        $pdf->sectionHead('الإيرادات');
        $pdf->tableHead(['الرمز', 'الحساب', 'صافي الإيراد'], $cols);
        $odd = false;
        foreach ($data['revenue'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $row['code'],          1, 0, 'C', true);
            $pdf->Cell($cols[1], 7, $row['name'],          1, 0, 'R', true);
            $pdf->Cell($cols[2], 7, PdfReport::n($row['net']), 1, 1, 'C', true);
            $odd = !$odd;
        }
        $pdf->totalsRow(['', 'إجمالي الإيرادات', PdfReport::n($data['total_revenue'])], $cols);
        $pdf->Ln(4);

        // Expense section
        $pdf->sectionHead('المصروفات');
        $pdf->tableHead(['الرمز', 'الحساب', 'صافي المصروف'], $cols);
        $odd = false;
        foreach ($data['expenses'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $row['code'],          1, 0, 'C', true);
            $pdf->Cell($cols[1], 7, $row['name'],          1, 0, 'R', true);
            $pdf->Cell($cols[2], 7, PdfReport::n($row['net']), 1, 1, 'C', true);
            $odd = !$odd;
        }
        $pdf->totalsRow(['', 'إجمالي المصروفات', PdfReport::n($data['total_expense'])], $cols);
        $pdf->Ln(6);

        // Net profit summary box
        $profit = (float) $data['net_profit'];
        $label  = $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة';
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->SetFillColor($data['is_profit'] ? 22 : 220, $data['is_profit'] ? 163 : 38, $data['is_profit'] ? 74 : 38);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(190, 10, "{$label}: " . PdfReport::n(abs($profit)), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        return $pdf->respond('income-statement.pdf');
    }

    public function balanceSheetPdf(Request $request): Response
    {
        $request->validate(['as_of' => ['nullable', 'date']]);
        $asOf = $request->input('as_of', now()->toDateString());
        $data = $this->balanceSheetData($asOf);

        $pdf  = PdfReport::make('الميزانية العمومية', "كما في تاريخ {$asOf}");
        $cols = [150, 40];

        // Assets
        $pdf->sectionHead('الأصول');
        $pdf->tableHead(['الحساب', 'الرصيد'], $cols);
        $odd = false;
        foreach ($data['assets'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, PdfReport::n($row['balance']), 1, 1, 'C', true);
            $odd = !$odd;
        }
        $pdf->totalsRow(['إجمالي الأصول', PdfReport::n($data['total_assets'])], $cols);
        $pdf->Ln(5);

        // Liabilities
        $pdf->sectionHead('الخصوم');
        $pdf->tableHead(['الحساب', 'الرصيد'], $cols);
        $odd = false;
        foreach ($data['liabilities'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, PdfReport::n($row['balance']), 1, 1, 'C', true);
            $odd = !$odd;
        }
        $pdf->totalsRow(['إجمالي الخصوم', PdfReport::n($data['total_liabilities'])], $cols);
        $pdf->Ln(5);

        // Equity
        $pdf->sectionHead('حقوق الملكية');
        $pdf->tableHead(['البند', 'المبلغ'], $cols);
        $odd = false;
        foreach ($data['equity'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, PdfReport::n($row['balance']), 1, 1, 'C', true);
            $odd = !$odd;
        }
        $profitLabel = $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة';
        $pdf->Cell($cols[0], 7, $profitLabel, 1, 0, 'R');
        $pdf->Cell($cols[1], 7, PdfReport::n(abs((float) $data['net_profit'])), 1, 1, 'C');
        $pdf->totalsRow(['إجمالي حقوق الملكية', PdfReport::n($data['total_equity_net'])], $cols);
        $pdf->Ln(5);

        // Balance check
        $pdf->SetFont('dejavusans', 'B', 10);
        $ok = $data['balanced'];
        $pdf->SetFillColor($ok ? 220 : 254, $ok ? 252 : 226, $ok ? 231 : 226);
        $pdf->SetTextColor($ok ? 21 : 153, $ok ? 128 : 27, $ok ? 61 : 27);
        $pdf->Cell(190, 8,
            ($ok ? '✓ الميزانية متوازنة — إجمالي الأصول = إجمالي الخصوم + حقوق الملكية = ' : '✗ غير متوازنة — ') .
            PdfReport::n($data['total_liab_equity']),
            1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        return $pdf->respond('balance-sheet.pdf');
    }

    // ─────────────────────────── Private query helpers ──────────────────────────

    private function validateDateRange(Request $request): array
    {
        $request->validate([
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy   = \App\Models\FiscalYear::findOrFail($fyId);
            $from = $fy->start_date->toDateString();
            $to   = $fy->end_date->toDateString();
        } else {
            $from = $request->input('from', now()->startOfYear()->toDateString());
            $to   = $request->input('to',   now()->toDateString());
        }

        return ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId];
    }

    /** Resolve from/to dates, preferring fiscal_year_id if provided. */
    private function resolveDates(Request $request, string $defaultFrom, string $defaultTo): array
    {
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy = \App\Models\FiscalYear::findOrFail($fyId);
            return [$fy->start_date->toDateString(), $fy->end_date->toDateString()];
        }
        return [
            $request->input('from', $defaultFrom),
            $request->input('to',   $defaultTo),
        ];
    }

    private function trialBalanceData(string $from, string $to, ?int $fiscalYearId = null): array
    {
        $journalRows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->get()->keyBy('account_id');

        $openings = DB::table('opening_balances as ob')
            ->join('accounts as a', 'a.id', '=', 'ob.account_id')
            ->when($fiscalYearId,
                fn ($q) => $q->where('ob.fiscal_year_id', $fiscalYearId),
                fn ($q) => $q->whereNull('ob.fiscal_year_id')
            )
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                'ob.debit as total_debit', 'ob.credit as total_credit')
            ->get()->keyBy('account_id');

        $allIds = $journalRows->keys()->merge($openings->keys())->unique();
        $rows = $allIds->map(function ($id) use ($journalRows, $openings) {
            $j     = $journalRows->get($id);
            $o     = $openings->get($id);
            $base  = $j ?? $o;
            $debit  = (float) ($j->total_debit  ?? 0) + (float) ($o->total_debit  ?? 0);
            $credit = (float) ($j->total_credit ?? 0) + (float) ($o->total_credit ?? 0);
            $net    = $debit - $credit;
            return [
                'account_id'     => $base->account_id,
                'code'           => $base->code,
                'name'           => $base->name,
                'type'           => $base->type,
                'total_debit'    => number_format($debit,        2, '.', ''),
                'total_credit'   => number_format($credit,       2, '.', ''),
                'balance'        => number_format(abs($net),     2, '.', ''),
                'balance_side'   => $net >= 0 ? 'debit' : 'credit',
                'balance_debit'  => number_format($net > 0 ? $net : 0,   2, '.', ''),
                'balance_credit' => number_format($net < 0 ? -$net : 0,  2, '.', ''),
            ];
        })->sortBy('code')->values();

        $totalDebit     = $rows->sum(fn ($r) => (float) $r['total_debit']);
        $totalCredit    = $rows->sum(fn ($r) => (float) $r['total_credit']);
        $totalBalDebit  = $rows->sum(fn ($r) => (float) $r['balance_debit']);
        $totalBalCredit = $rows->sum(fn ($r) => (float) $r['balance_credit']);

        return [
            'from'   => $from,
            'to'     => $to,
            'rows'   => $rows->values(),
            'totals' => [
                'debit'          => number_format($totalDebit,      2, '.', ''),
                'credit'         => number_format($totalCredit,     2, '.', ''),
                'balance_debit'  => number_format($totalBalDebit,   2, '.', ''),
                'balance_credit' => number_format($totalBalCredit,  2, '.', ''),
                'balanced'       => abs($totalDebit - $totalCredit) < 0.005,
            ],
        ];
    }

    private function ledgerData(int $accountId, string $from, string $to, ?int $partyId = null): array
    {
        $account     = Account::findOrFail($accountId);
        $debitNormal = in_array($account->type, ['asset', 'expense']);

        $storedOb    = DB::table('opening_balances')
            ->where('account_id', $accountId)
            ->whereNull('fiscal_year_id')   // ledger always uses global opening balance
            ->first();
        $storedObNet = $debitNormal
            ? ((float) ($storedOb->debit ?? 0) - (float) ($storedOb->credit ?? 0))
            : ((float) ($storedOb->credit ?? 0) - (float) ($storedOb->debit ?? 0));

        $prePeriod = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)->where('e.is_posted', true)->where('e.date', '<', $from)
            ->when($partyId, fn ($q) => $q->where('l.party_id', $partyId))
            ->selectRaw('SUM(l.debit) as d, SUM(l.credit) as c')->first();

        $openingBalance = $storedObNet + ($debitNormal
            ? ((float) ($prePeriod->d ?? 0) - (float) ($prePeriod->c ?? 0))
            : ((float) ($prePeriod->c ?? 0) - (float) ($prePeriod->d ?? 0)));

        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->leftJoin('parties as p', 'p.id', '=', 'l.party_id')
            ->where('l.account_id', $accountId)->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->when($partyId, fn ($q) => $q->where('l.party_id', $partyId))
            ->select('e.id as entry_id', 'e.date', 'e.reference', 'e.description as entry_description',
                'l.description as line_description', 'l.debit', 'l.credit', 'p.name as party_name')
            ->orderBy('e.date')->orderBy('e.id')->orderBy('l.id')->get();

        $running = $openingBalance;
        $rows = $lines->map(function ($line) use (&$running, $debitNormal) {
            $debit  = (float) $line->debit;
            $credit = (float) $line->credit;
            $running += $debitNormal ? ($debit - $credit) : ($credit - $debit);
            return [
                'entry_id'          => $line->entry_id,
                'date'              => $line->date,
                'reference'         => $line->reference,
                'entry_description' => $line->entry_description,
                'line_description'  => $line->line_description,
                'party_name'        => $line->party_name,
                'debit'             => number_format($debit,  2, '.', ''),
                'credit'            => number_format($credit, 2, '.', ''),
                'balance'           => number_format(abs($running), 2, '.', ''),
                'balance_side'      => $running >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            ];
        });

        $totalDebit  = $lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $lines->sum(fn ($l) => (float) $l->credit);
        $closing     = $openingBalance + ($debitNormal
            ? ($totalDebit - $totalCredit)
            : ($totalCredit - $totalDebit));

        return [
            'account'         => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'from'            => $from,
            'to'              => $to,
            'opening_balance' => number_format(abs($openingBalance), 2, '.', ''),
            'opening_side'    => $openingBalance >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            'closing_balance' => number_format(abs($closing), 2, '.', ''),
            'closing_side'    => $closing >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            'rows'            => $rows->values(),
            'totals'          => [
                'debit'  => number_format($totalDebit,  2, '.', ''),
                'credit' => number_format($totalCredit, 2, '.', ''),
            ],
        ];
    }

    private function incomeStatementData(string $from, string $to): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)->whereBetween('e.date', [$from, $to])
            ->whereIn('a.type', ['revenue', 'expense'])
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')->orderBy('a.type')->orderBy('a.code')->get()
            ->map(function ($row) {
                $debit  = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                $net    = $row->type === 'revenue' ? ($credit - $debit) : ($debit - $credit);
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

        return [
            'from'          => $from,
            'to'            => $to,
            'revenue'       => $rows->where('type', 'revenue')->values()->all(),
            'expenses'      => $rows->where('type', 'expense')->values()->all(),
            'total_revenue' => number_format($totalRevenue, 2, '.', ''),
            'total_expense' => number_format($totalExpense, 2, '.', ''),
            'net_profit'    => number_format($netProfit,    2, '.', ''),
            'is_profit'     => $netProfit >= 0,
        ];
    }

    private function balanceSheetData(string $asOf, ?int $fiscalYearId = null): array
    {
        $journalBalances = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)->where('e.date', '<=', $asOf)
            ->whereIn('a.type', ['asset', 'liability', 'equity', 'revenue', 'expense'])
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type', 'a.sub_type',
                DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.sub_type')->orderBy('a.code')->get()->keyBy('account_id');

        $openings = DB::table('opening_balances as ob')
            ->join('accounts as a', 'a.id', '=', 'ob.account_id')
            ->whereIn('a.type', ['asset', 'liability', 'equity'])
            ->when($fiscalYearId,
                fn ($q) => $q->where('ob.fiscal_year_id', $fiscalYearId),
                fn ($q) => $q->whereNull('ob.fiscal_year_id')
            )
            ->select('ob.account_id', 'a.code', 'a.name', 'a.type', 'a.sub_type',
                'ob.debit as total_debit', 'ob.credit as total_credit')
            ->get()->keyBy('account_id');

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
                'sub_type'     => $base->sub_type,
                'total_debit'  => (float) ($j->total_debit  ?? 0) + (float) ($o->total_debit  ?? 0),
                'total_credit' => (float) ($j->total_credit ?? 0) + (float) ($o->total_credit ?? 0),
            ];
        });

        $mapRow = fn ($row, bool $dn) => [
            'account_id' => $row->account_id,
            'code'       => $row->code,
            'name'       => $row->name,
            'balance'    => number_format($dn ? ($row->total_debit - $row->total_credit) : ($row->total_credit - $row->total_debit), 2, '.', ''),
        ];

        $assets      = $balances->where('type', 'asset')    ->map(fn ($r) => $mapRow($r, true))->values();
        $liabilities = $balances->where('type', 'liability')->map(fn ($r) => $mapRow($r, false))->values();
        $equity      = $balances->where('type', 'equity')  ->map(fn ($r) => $mapRow($r, false))->values();

        // Categorised for Form 2 (working capital format)
        $currentAssets     = $balances->where('type', 'asset')    ->where('sub_type', 'current')    ->map(fn ($r) => $mapRow($r, true))->values();
        $nonCurrentAssets  = $balances->where('type', 'asset')    ->where('sub_type', 'non_current')->map(fn ($r) => $mapRow($r, true))->values();
        $currentLiab       = $balances->where('type', 'liability')->where('sub_type', 'current')    ->map(fn ($r) => $mapRow($r, false))->values();
        $longTermLiab      = $balances->where('type', 'liability')->where('sub_type', 'long_term')  ->map(fn ($r) => $mapRow($r, false))->values();

        $revenue   = $balances->where('type', 'revenue')->sum(fn ($r) => (float) $r->total_credit - (float) $r->total_debit);
        $expense   = $balances->where('type', 'expense')->sum(fn ($r) => (float) $r->total_debit  - (float) $r->total_credit);
        $netProfit = $revenue - $expense;

        $totalAssets    = $assets->sum(fn ($r) => (float) $r['balance']);
        $totalLiab      = $liabilities->sum(fn ($r) => (float) $r['balance']);
        $totalEquity    = $equity->sum(fn ($r) => (float) $r['balance']);
        $totalEquityNet = $totalEquity + $netProfit;
        $totalLiabEquity = $totalLiab + $totalEquityNet;

        $totalCurrentAssets    = $currentAssets->sum(fn ($r) => (float) $r['balance']);
        $totalNonCurrentAssets = $nonCurrentAssets->sum(fn ($r) => (float) $r['balance']);
        $totalCurrentLiab      = $currentLiab->sum(fn ($r) => (float) $r['balance']);
        $totalLongTermLiab     = $longTermLiab->sum(fn ($r) => (float) $r['balance']);
        $workingCapital        = $totalCurrentAssets - $totalCurrentLiab;
        $totalAssetsForm2      = $workingCapital + $totalNonCurrentAssets;
        $netAssets             = $totalAssetsForm2 - $totalLongTermLiab;

        return [
            'as_of'                     => $asOf,
            'assets'                    => $assets->all(),
            'liabilities'               => $liabilities->all(),
            'equity'                    => $equity->all(),
            'net_profit'                => number_format($netProfit,           2, '.', ''),
            'is_profit'                 => $netProfit >= 0,
            'total_assets'              => number_format($totalAssets,         2, '.', ''),
            'total_liabilities'         => number_format($totalLiab,           2, '.', ''),
            'total_equity'              => number_format($totalEquity,         2, '.', ''),
            'total_equity_net'          => number_format($totalEquityNet,      2, '.', ''),
            'total_liab_equity'         => number_format($totalLiabEquity,     2, '.', ''),
            'balanced'                  => abs($totalAssets - $totalLiabEquity) < 0.005,
            // Form 2 (working capital format)
            'current_assets'            => $currentAssets->all(),
            'non_current_assets'        => $nonCurrentAssets->all(),
            'current_liabilities'       => $currentLiab->all(),
            'long_term_liabilities'     => $longTermLiab->all(),
            'total_current_assets'      => number_format($totalCurrentAssets,    2, '.', ''),
            'total_non_current_assets'  => number_format($totalNonCurrentAssets, 2, '.', ''),
            'total_current_liabilities' => number_format($totalCurrentLiab,      2, '.', ''),
            'total_long_term_liabilities'=> number_format($totalLongTermLiab,    2, '.', ''),
            'working_capital'           => number_format($workingCapital,         2, '.', ''),
            'net_assets'                => number_format($netAssets,              2, '.', ''),
        ];
    }
}
