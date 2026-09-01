<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Services\PdfReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        [$from, $to] = $this->resolveDates($request, now()->startOfYear()->toDateString(), now()->toDateString());
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;

        return response()->json($this->ledgerData(
            (int) $request->input('account_id'),
            $from,
            $to,
            $request->input('party_id') ? (int) $request->input('party_id') : null,
            $fyId
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
            'as_of' => ['nullable', 'date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy = FiscalYear::findOrFail($fyId);
            $asOf = $fy->end_date->toDateString();
        } else {
            $asOf = $request->input('as_of', now()->toDateString());
        }

        return response()->json($this->balanceSheetData($asOf, $fyId));
    }

    public function statementOfEquity(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId] = $this->validateDateRange($request);

        return response()->json($this->statementOfEquityData($from, $to, $fyId));
    }

    /**
     * Horizontal analysis (التحليل الأفقي) — the same balance sheet, taken "as
     * of" two different dates, compared line-by-line: absolute difference and
     * percentage difference per account, plus per-section subtotals.
     */
    public function balanceSheetHorizontal(Request $request): JsonResponse
    {
        $request->validate([
            'from_as_of' => ['nullable', 'date'],
            'from_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'to_as_of' => ['nullable', 'date'],
            'to_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        return response()->json($this->balanceSheetHorizontalData($request));
    }

    // ────────────────────────────── PDF endpoints ───────────────────────────────

    public function trialBalancePdf(Request $request): Response
    {
        ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId] = $this->validateDateRange($request);
        $viewType = $request->input('view_type', 'both'); // totals | balances | both
        $data = $this->trialBalanceData($from, $to, $fyId);

        $subtitles = [
            'totals' => 'بالمجاميع',
            'balances' => 'بالأرصدة',
            'both' => 'بالمجاميع والأرصدة',
        ];
        $subtitle = "الفترة من {$from} إلى {$to} — ".($subtitles[$viewType] ?? $subtitles['both']);
        $pdf = PdfReport::make('ميزان المراجعة', $subtitle);

        $typeLabels = ['asset' => 'أصول', 'liability' => 'خصوم', 'equity' => 'حقوق الملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات'];
        $typeOrder = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        $side = fn (string $s) => $s === 'debit' ? ' م' : ' د';

        if ($viewType === 'totals') {
            $cols = [18, 84, 30, 29, 29];
            $pdf->tableHead(['الرمز', 'اسم الحساب', 'رصيد أول الفترة', 'مدين الفترة', 'دائن الفترة'], $cols);
        } elseif ($viewType === 'balances') {
            $cols = [18, 84, 30, 29, 29];
            $pdf->tableHead(['الرمز', 'اسم الحساب', 'رصيد أول الفترة', 'رصيد مدين', 'رصيد دائن'], $cols);
        } else {
            $cols = [16, 50, 26, 24, 24, 25, 25];
            $pdf->tableHead(['الرمز', 'اسم الحساب', 'رصيد أول الفترة', 'مدين الفترة', 'دائن الفترة', 'رصيد مدين', 'رصيد دائن'], $cols);
        }

        $odd = false;
        foreach ($typeOrder as $type) {
            $group = collect($data['rows'])->where('type', $type)->values();
            if ($group->isEmpty()) {
                continue;
            }
            $pdf->sectionHead($typeLabels[$type]);

            foreach ($group as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['code'], 1, 0, 'C', true);
                $pdf->Cell($cols[1], 7, $row['name'], 1, 0, 'R', true);
                $pdf->Cell($cols[2], 7, PdfReport::n($row['opening_balance']).$side($row['opening_side']), 1, 0, 'C', true);
                if ($viewType === 'totals') {
                    $pdf->Cell($cols[3], 7, PdfReport::n($row['total_debit']), 1, 0, 'C', true);
                    $pdf->Cell($cols[4], 7, PdfReport::n($row['total_credit']), 1, 1, 'C', true);
                } elseif ($viewType === 'balances') {
                    $pdf->Cell($cols[3], 7, PdfReport::n($row['balance_debit']), 1, 0, 'C', true);
                    $pdf->Cell($cols[4], 7, PdfReport::n($row['balance_credit']), 1, 1, 'C', true);
                } else {
                    $pdf->Cell($cols[3], 7, PdfReport::n($row['total_debit']), 1, 0, 'C', true);
                    $pdf->Cell($cols[4], 7, PdfReport::n($row['total_credit']), 1, 0, 'C', true);
                    $pdf->Cell($cols[5], 7, PdfReport::n($row['balance_debit']), 1, 0, 'C', true);
                    $pdf->Cell($cols[6], 7, PdfReport::n($row['balance_credit']), 1, 1, 'C', true);
                }
                $odd = ! $odd;
            }
        }

        $t = $data['totals'];
        $openingTotal = PdfReport::n($t['opening_balance']).$side($t['opening_side']);
        if ($viewType === 'totals') {
            $pdf->totalsRow(
                ['الإجمالي', $openingTotal, PdfReport::n($t['debit']), PdfReport::n($t['credit'])],
                [$cols[0] + $cols[1], $cols[2], $cols[3], $cols[4]]
            );
        } elseif ($viewType === 'balances') {
            $pdf->totalsRow(
                ['الإجمالي', $openingTotal, PdfReport::n($t['balance_debit']), PdfReport::n($t['balance_credit'])],
                [$cols[0] + $cols[1], $cols[2], $cols[3], $cols[4]]
            );
        } else {
            $pdf->totalsRow(
                ['الإجمالي', $openingTotal, PdfReport::n($t['debit']), PdfReport::n($t['credit']), PdfReport::n($t['balance_debit']), PdfReport::n($t['balance_credit'])],
                [$cols[0] + $cols[1], $cols[2], $cols[3], $cols[4], $cols[5], $cols[6]]
            );
        }

        return $pdf->respond('trial-balance.pdf');
    }

    public function ledgerPdf(Request $request): Response
    {
        $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'view' => ['nullable', 'string', 'in:arabic,gl'],
        ]);
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        $data = $this->ledgerData(
            (int) $request->input('account_id'),
            $from,
            $to,
            $request->input('party_id') ? (int) $request->input('party_id') : null,
            $fyId
        );

        if ($request->input('view') === 'gl') {
            return $this->ledgerPdfGl($data, $from, $to);
        }

        $acct = $data['account'];
        $pdf = PdfReport::make(
            'كشف حساب: '.$acct['name'],
            "من {$from} إلى {$to}"
        );

        // Whole-number money: these amounts carry no meaningful fractional part,
        // and the trailing ".00" only pushed the numeric columns past their width
        // so adjacent values ran together.
        $money = fn ($v) => number_format(round((float) $v), 0, '.', ',');

        // Widths (mm) sum to 190 (A4 portrait content width). The three numeric
        // columns are wide enough for 8-digit thousands-separated values plus the
        // trailing " م"/" د" side marker; text cells are truncated with fit().
        $cols = [20, 15, 50, 28, 25, 25, 27];
        $pdf->tableHead(['التاريخ', 'مرجع', 'البيان', 'الطرف', 'مدين', 'دائن', 'الرصيد'], $cols);

        // Opening balance row
        $obSide = $data['opening_side'] === 'debit' ? ' م' : ' د';
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetFont('arial', '', 8);
        $pdf->Cell($cols[0], 6, $from, 1, 0, 'C', true);
        $pdf->Cell($cols[1], 6, '', 1, 0, 'C', true);
        $pdf->Cell($cols[2], 6, 'رصيد افتتاحي', 1, 0, 'R', true);
        $pdf->Cell($cols[3], 6, '', 1, 0, 'C', true);
        $pdf->Cell($cols[4], 6, '', 1, 0, 'C', true);
        $pdf->Cell($cols[5], 6, '', 1, 0, 'C', true);
        $pdf->Cell($cols[6], 6, $money($data['opening_balance']).$obSide, 1, 1, 'C', true, '', 1);
        $pdf->SetFont('arial', '', 9);

        $odd = false;
        foreach ($data['rows'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $side = $row['balance_side'] === 'debit' ? ' م' : ' د';
            $debit = (float) $row['debit'] > 0 ? $money($row['debit']) : '—';
            $credit = (float) $row['credit'] > 0 ? $money($row['credit']) : '—';

            $pdf->Cell($cols[0], 7, $row['date'], 1, 0, 'C', true);
            $pdf->Cell($cols[1], 7, $row['reference'] ?? '—', 1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, $pdf->fit((string) $row['entry_description'], $cols[2] - 3), 1, 0, 'R', true);
            $pdf->Cell($cols[3], 7, $pdf->fit((string) ($row['party_name'] ?? '—'), $cols[3] - 3), 1, 0, 'R', true);
            $pdf->Cell($cols[4], 7, $debit, 1, 0, 'C', true, '', 1);
            $pdf->Cell($cols[5], 7, $credit, 1, 0, 'C', true, '', 1);
            $pdf->Cell($cols[6], 7, $money($row['balance']).$side, 1, 1, 'C', true, '', 1);
            $odd = ! $odd;
        }

        $clSide = $data['closing_side'] === 'debit' ? ' م' : ' د';
        $pdf->totalsRow(
            ['الإجمالي', $money($data['totals']['debit']), $money($data['totals']['credit']), $money($data['closing_balance']).$clSide],
            [$cols[0] + $cols[1] + $cols[2] + $cols[3], $cols[4], $cols[5], $cols[6]]
        );

        return $pdf->respond('ledger.pdf');
    }

    /**
     * "General Ledger" styled PDF — mirrors the on-screen GeneralLedgerView:
     * bilingual title bar, red Account No./Name row, LTR columns with Dr/Cr balance.
     */
    private function ledgerPdfGl(array $data, string $from, string $to): Response
    {
        $acct = $data['account'];
        $pdf = PdfReport::make('كشف حساب: '.$acct['name'], "من {$from} إلى {$to}");
        $pdf->SetRTL(false);

        $money = fn ($v) => number_format(round((float) $v), 0, '.', ',');
        $sideEn = fn ($s) => $s === 'debit' ? 'Dr' : 'Cr';

        // Column widths (mm) — total 190 (A4 portrait content width)
        $w = [26, 22, 70, 24, 24, 24];
        $full = array_sum($w);
        $pageBottom = $pdf->getPageHeight() - 20;

        // ── Title bar ────────────────────────────────────────────────────────
        $pdf->SetDrawColor(13, 43, 110);
        $pdf->SetLineWidth(0.4);
        $pdf->SetFillColor(26, 58, 143);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('arialbd', '', 14);
        $pdf->Cell($full / 2, 11, 'General Ledger', 1, 0, 'C', true);
        $pdf->Cell($full / 2, 11, 'دفتر الأستاذ العام', 1, 1, 'C', true);

        // ── Account info row ─────────────────────────────────────────────────
        $pdf->SetFillColor(245, 248, 255);
        $pdf->SetTextColor(192, 0, 26);
        $pdf->SetFont('arialbd', '', 10);
        $pdf->Cell($full / 2, 8, 'Account No. : '.$acct['code'], 1, 0, 'L', true);
        $pdf->Cell($full / 2, 8, 'Account Name : '.$acct['name'], 1, 1, 'R', true);

        // ── Table header ─────────────────────────────────────────────────────
        $pdf->SetFillColor(26, 58, 143);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('arialbd', '', 9);
        foreach (array_map(null, ['Date', 'Trx. No.', 'Description', 'Debit', 'Credit', 'Balance'], $w) as [$label, $cw]) {
            $pdf->Cell($cw, 8, $label, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetDrawColor(205, 214, 240);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('arial', '', 8);

        // ── Opening balance row ──────────────────────────────────────────────
        $pdf->SetFillColor(240, 244, 255);
        $pdf->Cell($w[0], 7, $from, 1, 0, 'C', true);
        $pdf->Cell($w[1], 7, '', 1, 0, 'C', true);
        $pdf->SetFont('arialbd', '', 8);
        $pdf->Cell($w[2], 7, 'Opening Balance', 1, 0, 'C', true);
        $pdf->Cell($w[3], 7, '', 1, 0, 'C', true);
        $pdf->Cell($w[4], 7, '', 1, 0, 'C', true);
        $pdf->Cell($w[5], 7, $money($data['opening_balance']).' '.$sideEn($data['opening_side']), 1, 1, 'R', true);
        $pdf->SetFont('arial', '', 8);

        // ── Transaction rows ────────────────────────────────────────────────
        if (count($data['rows']) === 0) {
            $pdf->SetTextColor(136, 136, 136);
            $pdf->Cell($full, 12, 'No transactions in this period', 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        foreach ($data['rows'] as $row) {
            $parts = array_values(array_filter([
                $row['entry_description'],
                $row['line_description'] ?? null,
                $row['party_name'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
            $desc = implode("\n", $parts);

            $nb = max(1, $pdf->getNumLines($desc, $w[2]));
            $h = max(7, $nb * 4 + 2);

            if ($pdf->GetY() + $h > $pageBottom) {
                $pdf->AddPage();
            }
            $x = $pdf->GetX();
            $y = $pdf->GetY();

            $debit = (float) $row['debit'] > 0 ? $money($row['debit']) : '—';
            $credit = (float) $row['credit'] > 0 ? $money($row['credit']) : '—';
            $bal = $money($row['balance']).' '.$sideEn($row['balance_side']);

            $cell = function ($i, $txt, $align) use ($pdf, $w, $x, $y, $h) {
                $off = array_sum(array_slice($w, 0, $i));

                return $pdf->MultiCell($w[$i], $h, $txt, 1, $align, false, $i === 5 ? 1 : 0,
                    $x + $off, $y, true, 0, false, true, $h, 'M');
            };
            $cell(0, $row['date'], 'C');
            $cell(1, $row['reference'] ?? '—', 'C');
            $cell(2, $desc, 'L');
            $cell(3, $debit, 'R');
            $cell(4, $credit, 'R');
            $cell(5, $bal, 'R');
        }

        // ── Totals row ──────────────────────────────────────────────────────
        if (count($data['rows']) > 0) {
            if ($pdf->GetY() + 8 > $pageBottom) {
                $pdf->AddPage();
            }
            $pdf->SetDrawColor(13, 43, 110);
            $pdf->SetLineWidth(0.4);
            $pdf->SetFillColor(232, 238, 255);
            $pdf->SetFont('arialbd', '', 9);
            $pdf->Cell($w[0] + $w[1] + $w[2], 8, 'Total', 1, 0, 'C', true);
            $pdf->Cell($w[3], 8, $money($data['totals']['debit']), 1, 0, 'R', true);
            $pdf->Cell($w[4], 8, $money($data['totals']['credit']), 1, 0, 'R', true);
            $pdf->SetTextColor(192, 0, 26);
            $pdf->Cell($w[5], 8, $money($data['closing_balance']).' '.$sideEn($data['closing_side']), 1, 1, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
        }

        return $pdf->respond('ledger.pdf');
    }

    public function incomeStatementPdf(Request $request): Response
    {
        ['from' => $from, 'to' => $to] = $this->validateDateRange($request);
        $viewType = $request->input('view_type', 'columns'); // columns | statement
        $data = $this->incomeStatementData($from, $to);

        $pdf = PdfReport::make('قائمة الدخل', "الفترة من {$from} إلى {$to}");

        if ($viewType === 'statement') {
            // ── Formal statement layout: البيان | فرعي | إجمالي ──────────────
            $cols = [120, 35, 35];
            $pdf->tableHead(['البيان', 'فرعي', 'إجمالي'], $cols);

            // Revenue
            $pdf->sectionHead('الإيرادات');
            $odd = false;
            foreach ($data['revenue'] as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
                $pdf->Cell($cols[1], 7, PdfReport::n($row['net']), 1, 0, 'C', true);
                $pdf->Cell($cols[2], 7, '', 1, 1, 'C', true);
                $odd = ! $odd;
            }
            // Revenue total row: empty فرعي, total in إجمالي
            $pdf->SetFont('arialbd', '', 9);
            $pdf->SetFillColor(235, 252, 243);
            $pdf->Cell($cols[0], 7, 'إجمالي الإيرادات', 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, '', 1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, PdfReport::n($data['total_revenue']), 1, 1, 'C', true);
            $pdf->SetFont('arial', '', 9);
            $pdf->Ln(3);

            // Expenses
            $pdf->sectionHead('المصروفات (يطرح)');
            $odd = false;
            foreach ($data['expenses'] as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
                $pdf->Cell($cols[1], 7, PdfReport::n($row['net']), 1, 0, 'C', true);
                $pdf->Cell($cols[2], 7, '', 1, 1, 'C', true);
                $odd = ! $odd;
            }
            // Expenses total row
            $pdf->SetFont('arialbd', '', 9);
            $pdf->SetFillColor(254, 242, 242);
            $pdf->Cell($cols[0], 7, 'إجمالي المصروفات', 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, '', 1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, '('.PdfReport::n($data['total_expense']).')', 1, 1, 'C', true);
            $pdf->SetFont('arial', '', 9);
            $pdf->Ln(4);

            // Net profit/loss summary
            $profit = (float) $data['net_profit'];
            $label = $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة';
            $pdf->SetFont('arialbd', '', 11);
            $pdf->SetFillColor($data['is_profit'] ? 22 : 220, $data['is_profit'] ? 163 : 38, $data['is_profit'] ? 74 : 38);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($cols[0] + $cols[1], 10, $label, 1, 0, 'R', true);
            $pdf->Cell($cols[2], 10, PdfReport::n(abs($profit)), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
        } else {
            // ── Default columns layout: رمز | حساب | صافي ────────────────────
            $cols = [20, 140, 30];

            $pdf->sectionHead('الإيرادات');
            $pdf->tableHead(['الرمز', 'الحساب', 'صافي الإيراد'], $cols);
            $odd = false;
            foreach ($data['revenue'] as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['code'], 1, 0, 'C', true);
                $pdf->Cell($cols[1], 7, $row['name'], 1, 0, 'R', true);
                $pdf->Cell($cols[2], 7, PdfReport::n($row['net']), 1, 1, 'C', true);
                $odd = ! $odd;
            }
            $pdf->totalsRow(['', 'إجمالي الإيرادات', PdfReport::n($data['total_revenue'])], $cols);
            $pdf->Ln(4);

            $pdf->sectionHead('المصروفات');
            $pdf->tableHead(['الرمز', 'الحساب', 'صافي المصروف'], $cols);
            $odd = false;
            foreach ($data['expenses'] as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['code'], 1, 0, 'C', true);
                $pdf->Cell($cols[1], 7, $row['name'], 1, 0, 'R', true);
                $pdf->Cell($cols[2], 7, PdfReport::n($row['net']), 1, 1, 'C', true);
                $odd = ! $odd;
            }
            $pdf->totalsRow(['', 'إجمالي المصروفات', PdfReport::n($data['total_expense'])], $cols);
            $pdf->Ln(6);

            $profit = (float) $data['net_profit'];
            $label = $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة';
            $pdf->SetFont('arialbd', '', 11);
            $pdf->SetFillColor($data['is_profit'] ? 22 : 220, $data['is_profit'] ? 163 : 38, $data['is_profit'] ? 74 : 38);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(190, 10, "{$label}: ".PdfReport::n(abs($profit)), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
        }

        return $pdf->respond('income-statement.pdf');
    }

    public function balanceSheetPdf(Request $request): Response
    {
        $request->validate([
            'as_of' => ['nullable', 'date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy = FiscalYear::findOrFail($fyId);
            $asOf = $fy->end_date->toDateString();
        } else {
            $asOf = $request->input('as_of', now()->toDateString());
        }
        $data = $this->balanceSheetData($asOf, $fyId);

        $pdf = PdfReport::make('الميزانية العمومية', "كما في تاريخ {$asOf}");
        $cols = [150, 40];

        // Assets
        $pdf->sectionHead('الأصول');
        $pdf->tableHead(['الحساب', 'الرصيد'], $cols);
        $odd = false;
        foreach ($data['assets'] as $row) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, PdfReport::n($row['balance']), 1, 1, 'C', true);
            $odd = ! $odd;
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
            $odd = ! $odd;
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
            $odd = ! $odd;
        }
        $profitLabel = $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة';
        $pdf->Cell($cols[0], 7, $profitLabel, 1, 0, 'R');
        $pdf->Cell($cols[1], 7, PdfReport::n(abs((float) $data['net_profit'])), 1, 1, 'C');
        $pdf->totalsRow(['إجمالي حقوق الملكية', PdfReport::n($data['total_equity_net'])], $cols);
        $pdf->Ln(5);

        // Balance check
        $pdf->SetFont('arialbd', '', 10);
        $ok = $data['balanced'];
        $pdf->SetFillColor($ok ? 220 : 254, $ok ? 252 : 226, $ok ? 231 : 226);
        $pdf->SetTextColor($ok ? 21 : 153, $ok ? 128 : 27, $ok ? 61 : 27);
        $pdf->Cell(190, 8,
            ($ok ? '✓ الميزانية متوازنة — إجمالي الأصول = إجمالي الخصوم + حقوق الملكية = ' : '✗ غير متوازنة — ').
            PdfReport::n($data['total_liab_equity']),
            1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        return $pdf->respond('balance-sheet.pdf');
    }

    public function balanceSheetHorizontalPdf(Request $request): Response
    {
        $request->validate([
            'from_as_of' => ['nullable', 'date'],
            'from_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'to_as_of' => ['nullable', 'date'],
            'to_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $data = $this->balanceSheetHorizontalData($request);

        $pdf = PdfReport::make('التحليل الأفقي — قائمة المركز المالي', "من {$data['from_as_of']} إلى {$data['to_as_of']}");
        $cols = [64, 32, 32, 32, 30];
        $headLabels = ['الحساب', $data['from_as_of'], $data['to_as_of'], 'الفرق', '% الفرق'];

        $renderRows = function (array $rows) use ($pdf, $cols) {
            $odd = false;
            foreach ($rows as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $diff = (float) $row['diff'];
                $pdf->SetTextColor($diff < 0 ? 185 : 21, $diff < 0 ? 28 : 128, $diff < 0 ? 28 : 61);
                $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell($cols[1], 7, PdfReport::n($row['from']), 1, 0, 'C', true);
                $pdf->Cell($cols[2], 7, PdfReport::n($row['to']), 1, 0, 'C', true);
                $pdf->SetTextColor($diff < 0 ? 185 : 21, $diff < 0 ? 28 : 128, $diff < 0 ? 28 : 61);
                $pdf->Cell($cols[3], 7, PdfReport::n($row['diff']), 1, 0, 'C', true);
                $pdf->Cell($cols[4], 7, $row['percent'] === null ? '—' : number_format($row['percent'], 1).'%', 1, 1, 'C', true);
                $pdf->SetTextColor(0, 0, 0);
                $odd = ! $odd;
            }
        };

        $renderTotal = function (string $label, array $total) use ($pdf, $cols) {
            $diff = (float) $total['diff'];
            $pdf->SetFont('arialbd', '', 9);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Cell($cols[0], 7, $label, 1, 0, 'R', true);
            $pdf->Cell($cols[1], 7, PdfReport::n($total['from']), 1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, PdfReport::n($total['to']), 1, 0, 'C', true);
            $pdf->SetTextColor($diff < 0 ? 185 : 21, $diff < 0 ? 28 : 128, $diff < 0 ? 28 : 61);
            $pdf->Cell($cols[3], 7, PdfReport::n($total['diff']), 1, 0, 'C', true);
            $pdf->Cell($cols[4], 7, $total['percent'] === null ? '—' : number_format($total['percent'], 1).'%', 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('arial', '', 9);
        };

        $pdf->sectionHead('الأصول المتداولة');
        $pdf->tableHead($headLabels, $cols);
        $renderRows($data['current_assets']);
        $renderTotal('إجمالي الأصول المتداولة', $data['totals']['total_current_assets']);
        $pdf->Ln(3);

        $pdf->sectionHead('الأصول الثابتة');
        $pdf->tableHead($headLabels, $cols);
        $renderRows($data['non_current_assets']);
        $renderTotal('إجمالي الأصول الثابتة', $data['totals']['total_non_current_assets']);
        $renderTotal('إجمالي الموجودات', $data['totals']['total_assets']);
        $pdf->Ln(5);

        $pdf->sectionHead('الخصوم المتداولة');
        $pdf->tableHead($headLabels, $cols);
        $renderRows($data['current_liabilities']);
        $renderTotal('إجمالي الخصوم المتداولة', $data['totals']['total_current_liabilities']);
        $pdf->Ln(3);

        if (count($data['long_term_liabilities'])) {
            $pdf->sectionHead('الخصوم طويلة الأجل');
            $pdf->tableHead($headLabels, $cols);
            $renderRows($data['long_term_liabilities']);
            $renderTotal('إجمالي الخصوم طويلة الأجل', $data['totals']['total_long_term_liabilities']);
            $pdf->Ln(3);
        }

        $pdf->sectionHead('حقوق الملكية');
        $pdf->tableHead($headLabels, $cols);
        $renderRows($data['equity']);
        $renderTotal('إجمالي حقوق الملكية', $data['totals']['total_equity_net']);
        $renderTotal('إجمالي المطاليب وحقوق الملكية', $data['totals']['total_liab_equity']);

        return $pdf->respond('balance-sheet-horizontal.pdf');
    }

    public function statementOfEquityPdf(Request $request): Response
    {
        ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId] = $this->validateDateRange($request);
        $data = $this->statementOfEquityData($from, $to, $fyId);

        $pdf = PdfReport::make('قائمة التغير في حقوق الملكية', "الفترة من {$from} إلى {$to}");

        // Single column grid used throughout so vertical borders line up between
        // the summary rows (merged label + value) and the movements table below.
        $cols = [90, 33, 33, 34];
        $labelW = $cols[0] + $cols[1] + $cols[2];
        $valueW = $cols[3];

        $pdf->SetFont('arialbd', '', 9);
        $pdf->SetFillColor(51, 65, 85);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($labelW, 7, 'البيان', 1, 0, 'C', true);
        $pdf->Cell($valueW, 7, 'المبلغ', 1, 1, 'C', true);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('arial', '', 9);

        $pdf->Cell($labelW, 8, 'رصيد حقوق الملكية في بداية الفترة', 1, 0, 'R');
        $pdf->Cell($valueW, 8, PdfReport::n($data['beginning_balance']), 1, 1, 'C');

        $profitLabel = $data['is_profit'] ? 'يضاف: صافي الربح' : 'يخصم: صافي الخسارة';
        $pdf->SetTextColor($data['is_profit'] ? 22 : 220, $data['is_profit'] ? 100 : 38, $data['is_profit'] ? 40 : 38);
        $pdf->Cell($labelW, 8, $profitLabel, 1, 0, 'R');
        $pdf->Cell($valueW, 8, PdfReport::n(abs((float) $data['net_income'])), 1, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);

        if (count($data['movements'])) {
            $pdf->sectionHead('حركة حسابات حقوق الملكية خلال الفترة');
            $pdf->tableHead(['الحساب', 'إضافات', 'مسحوبات', 'الصافي'], $cols);
            $odd = false;
            foreach ($data['movements'] as $row) {
                $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
                $pdf->Cell($cols[0], 7, $row['name'], 1, 0, 'R', true);
                $pdf->Cell($cols[1], 7, PdfReport::n($row['contributions']), 1, 0, 'C', true);
                $pdf->Cell($cols[2], 7, PdfReport::n($row['withdrawals']), 1, 0, 'C', true);
                $pdf->Cell($cols[3], 7, PdfReport::n($row['net']), 1, 1, 'C', true);
                $odd = ! $odd;
            }
            $pdf->totalsRow(
                ['الإجمالي', PdfReport::n($data['total_contributions']), PdfReport::n($data['total_withdrawals']), PdfReport::n($data['net_movement'])],
                $cols
            );
            $pdf->Ln(4);
        }

        $pdf->SetFont('arialbd', '', 11);
        $pdf->SetFillColor(124, 58, 237);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($labelW, 10, 'رصيد حقوق الملكية في نهاية الفترة', 1, 0, 'R', true);
        $pdf->Cell($valueW, 10, PdfReport::n($data['ending_balance']), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        return $pdf->respond('statement-of-equity.pdf');
    }

    // ────────────────────────────── Excel endpoints ─────────────────────────────

    public function trialBalanceExcel(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId] = $this->validateDateRange($request);
        $data = $this->trialBalanceData($from, $to, $fyId);

        [$spreadsheet, $sheet] = $this->newSheet('ميزان المراجعة');
        $this->titleRows($sheet, 'ميزان المراجعة', "الفترة من {$from} إلى {$to}");

        $headers = ['الرمز', 'اسم الحساب', 'رصيد أول الفترة (مدين)', 'رصيد أول الفترة (دائن)', 'مدين الفترة', 'دائن الفترة', 'رصيد مدين', 'رصيد دائن'];
        $sheet->fromArray($headers, null, 'A4');
        $this->styleHeader($sheet, 'A4:H4');

        $typeLabels = ['asset' => 'أصول', 'liability' => 'خصوم', 'equity' => 'حقوق الملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات'];
        $row = 5;

        foreach (['asset', 'liability', 'equity', 'revenue', 'expense'] as $type) {
            $group = collect($data['rows'])->where('type', $type)->values();
            if ($group->isEmpty()) {
                continue;
            }
            $sheet->setCellValue("A{$row}", $typeLabels[$type]);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            ]);
            $row++;

            foreach ($group as $r) {
                $openDebit = $r['opening_side'] === 'debit' ? (float) $r['opening_balance'] : 0;
                $openCredit = $r['opening_side'] === 'credit' ? (float) $r['opening_balance'] : 0;
                $sheet->fromArray([
                    $r['code'], $r['name'], $openDebit, $openCredit,
                    (float) $r['total_debit'], (float) $r['total_credit'],
                    (float) $r['balance_debit'], (float) $r['balance_credit'],
                ], null, "A{$row}");
                $row++;
            }
        }

        $t = $data['totals'];
        $sheet->setCellValue("B{$row}", 'الإجمالي');
        $sheet->setCellValue("C{$row}", $t['opening_side'] === 'debit' ? (float) $t['opening_balance'] : 0);
        $sheet->setCellValue("D{$row}", $t['opening_side'] === 'credit' ? (float) $t['opening_balance'] : 0);
        $sheet->setCellValue("E{$row}", (float) $t['debit']);
        $sheet->setCellValue("F{$row}", (float) $t['credit']);
        $sheet->setCellValue("G{$row}", (float) $t['balance_debit']);
        $sheet->setCellValue("H{$row}", (float) $t['balance_credit']);
        $this->styleTotals($sheet, "A{$row}:H{$row}");

        $this->numberFormat($sheet, "C5:H{$row}");
        $this->autoSize($sheet, 'H');

        return $this->xlsx($spreadsheet, 'trial-balance.xlsx');
    }

    public function incomeStatementExcel(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->validateDateRange($request);
        $data = $this->incomeStatementData($from, $to);

        [$spreadsheet, $sheet] = $this->newSheet('قائمة الدخل');
        $this->titleRows($sheet, 'قائمة الدخل', "عن الفترة من {$from} إلى {$to}");

        $sheet->fromArray(['الرمز', 'الحساب', 'صافي'], null, 'A4');
        $this->styleHeader($sheet, 'A4:C4');
        $row = 5;

        $section = function (string $label) use ($sheet, &$row) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            ]);
            $row++;
        };

        $section('الإيرادات');
        foreach ($data['revenue'] as $r) {
            $sheet->fromArray([$r['code'], $r['name'], (float) $r['net']], null, "A{$row}");
            $row++;
        }
        $sheet->setCellValue("B{$row}", 'إجمالي الإيرادات');
        $sheet->setCellValue("C{$row}", (float) $data['total_revenue']);
        $this->styleTotals($sheet, "A{$row}:C{$row}");
        $row += 2;

        $section('المصروفات');
        foreach ($data['expenses'] as $r) {
            $sheet->fromArray([$r['code'], $r['name'], (float) $r['net']], null, "A{$row}");
            $row++;
        }
        $sheet->setCellValue("B{$row}", 'إجمالي المصروفات');
        $sheet->setCellValue("C{$row}", (float) $data['total_expense']);
        $this->styleTotals($sheet, "A{$row}:C{$row}");
        $row += 2;

        $sheet->setCellValue("B{$row}", $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة');
        $sheet->setCellValue("C{$row}", abs((float) $data['net_profit']));
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $data['is_profit'] ? '16A34A' : 'DC2626']],
        ]);

        $this->numberFormat($sheet, "C5:C{$row}");
        $this->autoSize($sheet, 'C');

        return $this->xlsx($spreadsheet, 'income-statement.xlsx');
    }

    public function balanceSheetExcel(Request $request): StreamedResponse
    {
        $request->validate([
            'as_of' => ['nullable', 'date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        $asOf = $fyId
            ? FiscalYear::findOrFail($fyId)->end_date->toDateString()
            : $request->input('as_of', now()->toDateString());
        $data = $this->balanceSheetData($asOf, $fyId);

        [$spreadsheet, $sheet] = $this->newSheet('الميزانية العمومية');
        $this->titleRows($sheet, 'الميزانية العمومية', "كما في تاريخ {$asOf}");

        $sheet->fromArray(['الحساب', 'الرصيد'], null, 'A4');
        $this->styleHeader($sheet, 'A4:B4');
        $row = 5;

        $section = function (string $label) use ($sheet, &$row) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            ]);
            $row++;
        };
        $total = function (string $label, string $value) use ($sheet, &$row) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", (float) $value);
            $this->styleTotals($sheet, "A{$row}:B{$row}");
            $row++;
        };

        $section('الأصول');
        foreach ($data['assets'] as $r) {
            $sheet->fromArray([$r['name'], (float) $r['balance']], null, "A{$row}");
            $row++;
        }
        $total('إجمالي الأصول', $data['total_assets']);
        $row++;

        $section('الخصوم');
        foreach ($data['liabilities'] as $r) {
            $sheet->fromArray([$r['name'], (float) $r['balance']], null, "A{$row}");
            $row++;
        }
        $total('إجمالي الخصوم', $data['total_liabilities']);
        $row++;

        $section('حقوق الملكية');
        foreach ($data['equity'] as $r) {
            $sheet->fromArray([$r['name'], (float) $r['balance']], null, "A{$row}");
            $row++;
        }
        $sheet->setCellValue("A{$row}", $data['is_profit'] ? 'صافي الربح' : 'صافي الخسارة');
        $sheet->setCellValue("B{$row}", abs((float) $data['net_profit']));
        $row++;
        $total('إجمالي حقوق الملكية', $data['total_equity_net']);
        $row++;

        $sheet->setCellValue("A{$row}", $data['balanced'] ? 'الميزانية متوازنة (الأصول = الخصوم + حقوق الملكية)' : 'غير متوازنة');
        $sheet->setCellValue("B{$row}", (float) $data['total_liab_equity']);
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $data['balanced'] ? 'DCFCE7' : 'FEE2E2']],
        ]);

        $this->numberFormat($sheet, "B5:B{$row}");
        $this->autoSize($sheet, 'B');

        return $this->xlsx($spreadsheet, 'balance-sheet.xlsx');
    }

    public function balanceSheetHorizontalExcel(Request $request): StreamedResponse
    {
        $request->validate([
            'from_as_of' => ['nullable', 'date'],
            'from_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'to_as_of' => ['nullable', 'date'],
            'to_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $data = $this->balanceSheetHorizontalData($request);

        [$spreadsheet, $sheet] = $this->newSheet('التحليل الأفقي');
        $this->titleRows($sheet, 'التحليل الأفقي — قائمة المركز المالي', "من {$data['from_as_of']} إلى {$data['to_as_of']}");

        $sheet->fromArray(['الحساب', $data['from_as_of'], $data['to_as_of'], 'الفرق', '% الفرق'], null, 'A4');
        $this->styleHeader($sheet, 'A4:E4');
        $row = 5;

        $section = function (string $label) use ($sheet, &$row) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            ]);
            $row++;
        };
        $line = function (array $r) use ($sheet, &$row) {
            $sheet->fromArray([
                $r['name'], (float) $r['from'], (float) $r['to'], (float) $r['diff'],
                $r['percent'] === null ? '—' : $r['percent'] / 100,
            ], null, "A{$row}");
            if ($r['percent'] !== null) {
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.0%');
            }
            $row++;
        };
        $total = function (string $label, array $t) use ($sheet, &$row) {
            $sheet->fromArray([
                $label, (float) $t['from'], (float) $t['to'], (float) $t['diff'],
                $t['percent'] === null ? '—' : $t['percent'] / 100,
            ], null, "A{$row}");
            $this->styleTotals($sheet, "A{$row}:E{$row}");
            if ($t['percent'] !== null) {
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.0%');
            }
            $row++;
        };

        $section('الأصول المتداولة');
        foreach ($data['current_assets'] as $r) {
            $line($r);
        }
        $total('إجمالي الأصول المتداولة', $data['totals']['total_current_assets']);
        $row++;

        $section('الأصول الثابتة');
        foreach ($data['non_current_assets'] as $r) {
            $line($r);
        }
        $total('إجمالي الأصول الثابتة', $data['totals']['total_non_current_assets']);
        $total('إجمالي الموجودات', $data['totals']['total_assets']);
        $row++;

        $section('الخصوم المتداولة');
        foreach ($data['current_liabilities'] as $r) {
            $line($r);
        }
        $total('إجمالي الخصوم المتداولة', $data['totals']['total_current_liabilities']);
        $row++;

        if (count($data['long_term_liabilities'])) {
            $section('الخصوم طويلة الأجل');
            foreach ($data['long_term_liabilities'] as $r) {
                $line($r);
            }
            $total('إجمالي الخصوم طويلة الأجل', $data['totals']['total_long_term_liabilities']);
            $row++;
        }

        $section('حقوق الملكية');
        foreach ($data['equity'] as $r) {
            $line($r);
        }
        $total('إجمالي حقوق الملكية', $data['totals']['total_equity_net']);
        $total('إجمالي المطاليب وحقوق الملكية', $data['totals']['total_liab_equity']);

        $this->numberFormat($sheet, "B5:D{$row}");
        $this->autoSize($sheet, 'E');

        return $this->xlsx($spreadsheet, 'balance-sheet-horizontal.xlsx');
    }

    public function statementOfEquityExcel(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId] = $this->validateDateRange($request);
        $data = $this->statementOfEquityData($from, $to, $fyId);

        [$spreadsheet, $sheet] = $this->newSheet('التغير في حقوق الملكية');
        $this->titleRows($sheet, 'قائمة التغير في حقوق الملكية', "عن الفترة من {$from} إلى {$to}");

        $sheet->fromArray(['البيان', 'المبلغ'], null, 'A4');
        $this->styleHeader($sheet, 'A4:B4');
        $row = 5;

        $sheet->fromArray(['رصيد حقوق الملكية في بداية الفترة', (float) $data['beginning_balance']], null, "A{$row}");
        $row++;
        $sheet->fromArray([
            $data['is_profit'] ? 'يضاف: صافي الربح' : 'يخصم: صافي الخسارة',
            abs((float) $data['net_income']),
        ], null, "A{$row}");
        $row += 2;

        if (count($data['movements'])) {
            $sheet->fromArray(['الحساب', 'إضافات', 'مسحوبات', 'الصافي'], null, "A{$row}");
            $this->styleHeader($sheet, "A{$row}:D{$row}");
            $row++;
            foreach ($data['movements'] as $r) {
                $sheet->fromArray([
                    $r['name'], (float) $r['contributions'], (float) $r['withdrawals'], (float) $r['net'],
                ], null, "A{$row}");
                $row++;
            }
            $sheet->fromArray([
                'الإجمالي', (float) $data['total_contributions'], (float) $data['total_withdrawals'], (float) $data['net_movement'],
            ], null, "A{$row}");
            $this->styleTotals($sheet, "A{$row}:D{$row}");
            $this->numberFormat($sheet, 'B'.($row - count($data['movements'])).":D{$row}");
            $row += 2;
        }

        $sheet->setCellValue("A{$row}", 'رصيد حقوق الملكية في نهاية الفترة');
        $sheet->setCellValue("B{$row}", (float) $data['ending_balance']);
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
        ]);

        $this->numberFormat($sheet, "B5:B{$row}");
        $this->autoSize($sheet, 'D');

        return $this->xlsx($spreadsheet, 'statement-of-equity.xlsx');
    }

    // ─────────────────────────── Excel helpers ──────────────────────────────────

    /** @return array{0: Spreadsheet, 1: Worksheet} */
    private function newSheet(string $title): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->setRightToLeft(true);

        return [$spreadsheet, $sheet];
    }

    private function titleRows(Worksheet $sheet, string $title, string $subtitle): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A2')->getFont()->setItalic(true);
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1565C0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function styleTotals(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
    }

    private function numberFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    private function autoSize(Worksheet $sheet, string $lastCol): void
    {
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function xlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────── Private query helpers ──────────────────────────

    private function validateDateRange(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy = FiscalYear::findOrFail($fyId);
            $from = $fy->start_date->toDateString();
            $to = $fy->end_date->toDateString();
        } else {
            $from = $request->input('from', now()->startOfYear()->toDateString());
            $to = $request->input('to', now()->toDateString());
        }

        return ['from' => $from, 'to' => $to, 'fiscal_year_id' => $fyId];
    }

    /** Resolve from/to dates, preferring fiscal_year_id if provided. */
    private function resolveDates(Request $request, string $defaultFrom, string $defaultTo): array
    {
        $fyId = $request->input('fiscal_year_id') ? (int) $request->input('fiscal_year_id') : null;
        if ($fyId) {
            $fy = FiscalYear::findOrFail($fyId);

            return [$fy->start_date->toDateString(), $fy->end_date->toDateString()];
        }

        return [
            $request->input('from', $defaultFrom),
            $request->input('to', $defaultTo),
        ];
    }

    private function trialBalanceData(string $from, string $to, ?int $fiscalYearId = null): array
    {
        $periodRows = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->whereBetween('e.date', [$from, $to])
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit) as debit'),
                DB::raw('SUM(l.credit) as credit'),
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
                'ob.debit as debit', 'ob.credit as credit')
            ->get()->keyBy('account_id');

        // Activity before the selected `from` date, needed so the opening-balance column
        // reflects the true balance at the start of the selected period when using the
        // global (non-fiscal-year-scoped) opening balance. Skipped when a fiscal_year_id
        // is given: `from` is then always that fiscal year's own start_date, and its
        // stored opening balance already accounts for everything before that date —
        // re-summing it here would double count.
        $prePeriod = $fiscalYearId ? collect() : DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->where('e.date', '<', $from)
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit) as debit'),
                DB::raw('SUM(l.credit) as credit'),
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->get()->keyBy('account_id');

        $allIds = $periodRows->keys()->merge($openings->keys())->merge($prePeriod->keys())->unique();
        $rows = $allIds->map(function ($id) use ($periodRows, $openings, $prePeriod) {
            $pr = $periodRows->get($id);
            $ob = $openings->get($id);
            $pp = $prePeriod->get($id);
            $base = $pr ?? $ob ?? $pp;

            $openingDebit = (float) ($ob->debit ?? 0) + (float) ($pp->debit ?? 0);
            $openingCredit = (float) ($ob->credit ?? 0) + (float) ($pp->credit ?? 0);
            $openingNet = $openingDebit - $openingCredit;

            $periodDebit = (float) ($pr->debit ?? 0);
            $periodCredit = (float) ($pr->credit ?? 0);
            $net = $openingNet + $periodDebit - $periodCredit;

            return [
                'account_id' => $base->account_id,
                'code' => $base->code,
                'name' => $base->name,
                'type' => $base->type,
                'opening_balance' => number_format(abs($openingNet), 2, '.', ''),
                'opening_side' => $openingNet >= 0 ? 'debit' : 'credit',
                'total_debit' => number_format($periodDebit, 2, '.', ''),
                'total_credit' => number_format($periodCredit, 2, '.', ''),
                'balance' => number_format(abs($net), 2, '.', ''),
                'balance_side' => $net >= 0 ? 'debit' : 'credit',
                'balance_debit' => number_format($net > 0 ? $net : 0, 2, '.', ''),
                'balance_credit' => number_format($net < 0 ? -$net : 0, 2, '.', ''),
            ];
        })->sortBy('code')->values();

        $totalOpeningDebit = $rows->sum(fn ($r) => $r['opening_side'] === 'debit' ? (float) $r['opening_balance'] : 0);
        $totalOpeningCredit = $rows->sum(fn ($r) => $r['opening_side'] === 'credit' ? (float) $r['opening_balance'] : 0);
        $totalOpeningNet = $totalOpeningDebit - $totalOpeningCredit;
        $totalDebit = $rows->sum(fn ($r) => (float) $r['total_debit']);
        $totalCredit = $rows->sum(fn ($r) => (float) $r['total_credit']);
        $totalBalDebit = $rows->sum(fn ($r) => (float) $r['balance_debit']);
        $totalBalCredit = $rows->sum(fn ($r) => (float) $r['balance_credit']);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows->values(),
            'totals' => [
                'opening_balance' => number_format(abs($totalOpeningNet), 2, '.', ''),
                'opening_side' => $totalOpeningNet >= 0 ? 'debit' : 'credit',
                'debit' => number_format($totalDebit, 2, '.', ''),
                'credit' => number_format($totalCredit, 2, '.', ''),
                'balance_debit' => number_format($totalBalDebit, 2, '.', ''),
                'balance_credit' => number_format($totalBalCredit, 2, '.', ''),
                'balanced' => abs($totalBalDebit - $totalBalCredit) < 0.005,
            ],
        ];
    }

    private function ledgerData(int $accountId, string $from, string $to, ?int $partyId = null, ?int $fiscalYearId = null): array
    {
        $account = Account::findOrFail($accountId);
        $debitNormal = in_array($account->type, ['asset', 'expense']);

        $storedOb = DB::table('opening_balances')
            ->where('account_id', $accountId)
            ->when(
                $fiscalYearId,
                fn ($q) => $q->where('fiscal_year_id', $fiscalYearId),
                fn ($q) => $q->whereNull('fiscal_year_id')
            )
            ->first();
        $storedObNet = $debitNormal
            ? ((float) ($storedOb->debit ?? 0) - (float) ($storedOb->credit ?? 0))
            : ((float) ($storedOb->credit ?? 0) - (float) ($storedOb->debit ?? 0));

        // Skipped when a fiscal_year_id is given: `from` is then always that fiscal
        // year's own start_date, and its stored opening balance already accounts for
        // everything before that date — re-summing it here would double count.
        $prePeriod = $fiscalYearId ? (object) ['d' => 0, 'c' => 0] : DB::table('journal_entry_lines as l')
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
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $running += $debitNormal ? ($debit - $credit) : ($credit - $debit);

            return [
                'entry_id' => $line->entry_id,
                'date' => $line->date,
                'reference' => $line->reference,
                'entry_description' => $line->entry_description,
                'line_description' => $line->line_description,
                'party_name' => $line->party_name,
                'debit' => number_format($debit, 2, '.', ''),
                'credit' => number_format($credit, 2, '.', ''),
                'balance' => number_format(abs($running), 2, '.', ''),
                'balance_side' => $running >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            ];
        });

        $totalDebit = $lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $lines->sum(fn ($l) => (float) $l->credit);
        $closing = $openingBalance + ($debitNormal
            ? ($totalDebit - $totalCredit)
            : ($totalCredit - $totalDebit));

        return [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'from' => $from,
            'to' => $to,
            'opening_balance' => number_format(abs($openingBalance), 2, '.', ''),
            'opening_side' => $openingBalance >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            'closing_balance' => number_format(abs($closing), 2, '.', ''),
            'closing_side' => $closing >= 0 ? ($debitNormal ? 'debit' : 'credit') : ($debitNormal ? 'credit' : 'debit'),
            'rows' => $rows->values(),
            'totals' => [
                'debit' => number_format($totalDebit, 2, '.', ''),
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
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                $net = $row->type === 'revenue' ? ($credit - $debit) : ($debit - $credit);

                return [
                    'account_id' => $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'total_debit' => number_format($debit, 2, '.', ''),
                    'total_credit' => number_format($credit, 2, '.', ''),
                    'net' => number_format($net, 2, '.', ''),
                ];
            });

        $totalRevenue = $rows->where('type', 'revenue')->sum(fn ($r) => (float) $r['net']);
        $totalExpense = $rows->where('type', 'expense')->sum(fn ($r) => (float) $r['net']);
        $netProfit = $totalRevenue - $totalExpense;

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $rows->where('type', 'revenue')->values()->all(),
            'expenses' => $rows->where('type', 'expense')->values()->all(),
            'total_revenue' => number_format($totalRevenue, 2, '.', ''),
            'total_expense' => number_format($totalExpense, 2, '.', ''),
            'net_profit' => number_format($netProfit, 2, '.', ''),
            'is_profit' => $netProfit >= 0,
        ];
    }

    private function balanceSheetData(string $asOf, ?int $fiscalYearId = null): array
    {
        // When scoped to a fiscal year, only sum that year's own journal activity: its
        // stored opening balance already accounts for everything before its start_date,
        // so summing every entry since the beginning of time here would double count.
        $journalBalancesQuery = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)->where('e.date', '<=', $asOf);

        if ($fiscalYearId) {
            $journalBalancesQuery->where('e.date', '>=', FiscalYear::findOrFail($fiscalYearId)->start_date->toDateString());
        }

        $journalBalances = $journalBalancesQuery
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
                'account_id' => $base->account_id,
                'code' => $base->code,
                'name' => $base->name,
                'type' => $base->type,
                'sub_type' => $base->sub_type,
                'total_debit' => (float) ($j->total_debit ?? 0) + (float) ($o->total_debit ?? 0),
                'total_credit' => (float) ($j->total_credit ?? 0) + (float) ($o->total_credit ?? 0),
            ];
        });

        $mapRow = fn ($row, bool $dn) => [
            'account_id' => $row->account_id,
            'code' => $row->code,
            'name' => $row->name,
            'balance' => number_format($dn ? ($row->total_debit - $row->total_credit) : ($row->total_credit - $row->total_debit), 2, '.', ''),
        ];

        $assets = $balances->where('type', 'asset')->map(fn ($r) => $mapRow($r, true))->values();
        $liabilities = $balances->where('type', 'liability')->map(fn ($r) => $mapRow($r, false))->values();
        $equity = $balances->where('type', 'equity')->map(fn ($r) => $mapRow($r, false))->values();

        // Categorised for Form 2 (working capital format)
        $currentAssets = $balances->where('type', 'asset')->where('sub_type', 'current')->map(fn ($r) => $mapRow($r, true))->values();
        $nonCurrentAssets = $balances->where('type', 'asset')->where('sub_type', 'non_current')->map(fn ($r) => $mapRow($r, true))->values();
        $currentLiab = $balances->where('type', 'liability')->where('sub_type', 'current')->map(fn ($r) => $mapRow($r, false))->values();
        $longTermLiab = $balances->where('type', 'liability')->where('sub_type', 'long_term')->map(fn ($r) => $mapRow($r, false))->values();

        $revenue = $balances->where('type', 'revenue')->sum(fn ($r) => (float) $r->total_credit - (float) $r->total_debit);
        $expense = $balances->where('type', 'expense')->sum(fn ($r) => (float) $r->total_debit - (float) $r->total_credit);
        $netProfit = $revenue - $expense;

        $totalAssets = $assets->sum(fn ($r) => (float) $r['balance']);
        $totalLiab = $liabilities->sum(fn ($r) => (float) $r['balance']);
        $totalEquity = $equity->sum(fn ($r) => (float) $r['balance']);
        $totalEquityNet = $totalEquity + $netProfit;
        $totalLiabEquity = $totalLiab + $totalEquityNet;

        $totalCurrentAssets = $currentAssets->sum(fn ($r) => (float) $r['balance']);
        $totalNonCurrentAssets = $nonCurrentAssets->sum(fn ($r) => (float) $r['balance']);
        $totalCurrentLiab = $currentLiab->sum(fn ($r) => (float) $r['balance']);
        $totalLongTermLiab = $longTermLiab->sum(fn ($r) => (float) $r['balance']);
        $workingCapital = $totalCurrentAssets - $totalCurrentLiab;
        $totalAssetsForm2 = $workingCapital + $totalNonCurrentAssets;
        $netAssets = $totalAssetsForm2 - $totalLongTermLiab;

        return [
            'as_of' => $asOf,
            'assets' => $assets->all(),
            'liabilities' => $liabilities->all(),
            'equity' => $equity->all(),
            'net_profit' => number_format($netProfit, 2, '.', ''),
            'is_profit' => $netProfit >= 0,
            'total_assets' => number_format($totalAssets, 2, '.', ''),
            'total_liabilities' => number_format($totalLiab, 2, '.', ''),
            'total_equity' => number_format($totalEquity, 2, '.', ''),
            'total_equity_net' => number_format($totalEquityNet, 2, '.', ''),
            'total_liab_equity' => number_format($totalLiabEquity, 2, '.', ''),
            'balanced' => abs($totalAssets - $totalLiabEquity) < 0.005,
            // Form 2 (working capital format)
            'current_assets' => $currentAssets->all(),
            'non_current_assets' => $nonCurrentAssets->all(),
            'current_liabilities' => $currentLiab->all(),
            'long_term_liabilities' => $longTermLiab->all(),
            'total_current_assets' => number_format($totalCurrentAssets, 2, '.', ''),
            'total_non_current_assets' => number_format($totalNonCurrentAssets, 2, '.', ''),
            'total_current_liabilities' => number_format($totalCurrentLiab, 2, '.', ''),
            'total_long_term_liabilities' => number_format($totalLongTermLiab, 2, '.', ''),
            'working_capital' => number_format($workingCapital, 2, '.', ''),
            'net_assets' => number_format($netAssets, 2, '.', ''),
        ];
    }

    /**
     * @return array{from_as_of: string, to_as_of: string, current_assets: array, non_current_assets: array,
     *     current_liabilities: array, long_term_liabilities: array, equity: array, totals: array}
     */
    private function balanceSheetHorizontalData(Request $request): array
    {
        $toAsOf = $this->resolveAsOf($request, 'to_fiscal_year_id', 'to_as_of', now()->toDateString());
        $fromAsOf = $this->resolveAsOf($request, 'from_fiscal_year_id', 'from_as_of', now()->subYear()->toDateString());

        $from = $this->balanceSheetData($fromAsOf);
        $to = $this->balanceSheetData($toAsOf);

        return [
            'from_as_of' => $fromAsOf,
            'to_as_of' => $toAsOf,
            'current_assets' => $this->horizontalRows($from['current_assets'], $to['current_assets']),
            'non_current_assets' => $this->horizontalRows($from['non_current_assets'], $to['non_current_assets']),
            'current_liabilities' => $this->horizontalRows($from['current_liabilities'], $to['current_liabilities']),
            'long_term_liabilities' => $this->horizontalRows($from['long_term_liabilities'], $to['long_term_liabilities']),
            'equity' => $this->horizontalRows($from['equity'], $to['equity']),
            'totals' => [
                'total_current_assets' => $this->diffTotal($from['total_current_assets'], $to['total_current_assets']),
                'total_non_current_assets' => $this->diffTotal($from['total_non_current_assets'], $to['total_non_current_assets']),
                'total_assets' => $this->diffTotal($from['total_assets'], $to['total_assets']),
                'total_current_liabilities' => $this->diffTotal($from['total_current_liabilities'], $to['total_current_liabilities']),
                'total_long_term_liabilities' => $this->diffTotal($from['total_long_term_liabilities'], $to['total_long_term_liabilities']),
                'total_equity_net' => $this->diffTotal($from['total_equity_net'], $to['total_equity_net']),
                'total_liab_equity' => $this->diffTotal($from['total_liab_equity'], $to['total_liab_equity']),
            ],
        ];
    }

    /** Resolves an "as of" date from either a fiscal year (→ its end date) or an explicit date, falling back to $default. */
    private function resolveAsOf(Request $request, string $fyField, string $dateField, string $default): string
    {
        $fyId = $request->input($fyField) ? (int) $request->input($fyField) : null;
        if ($fyId) {
            return FiscalYear::findOrFail($fyId)->end_date->toDateString();
        }

        return $request->input($dateField, $default);
    }

    /**
     * Pairs up the same account across two balance-sheet snapshots by account_id
     * (an account present in only one period is treated as zero in the other),
     * and computes the absolute and percentage difference between them.
     *
     * @param  array<int, array{account_id: int, code: string, name: string, balance: string}>  $fromRows
     * @param  array<int, array{account_id: int, code: string, name: string, balance: string}>  $toRows
     */
    private function horizontalRows(array $fromRows, array $toRows): array
    {
        $from = collect($fromRows)->keyBy('account_id');
        $to = collect($toRows)->keyBy('account_id');

        return $from->keys()->merge($to->keys())->unique()
            ->map(function ($id) use ($from, $to) {
                $f = $from->get($id);
                $t = $to->get($id);
                $fromBalance = (float) ($f['balance'] ?? 0);
                $toBalance = (float) ($t['balance'] ?? 0);

                return [
                    'account_id' => $id,
                    'code' => $t['code'] ?? $f['code'],
                    'name' => $t['name'] ?? $f['name'],
                    ...$this->diffTotal(
                        number_format($fromBalance, 2, '.', ''),
                        number_format($toBalance, 2, '.', '')
                    ),
                ];
            })
            ->sortBy('code')->values()->all();
    }

    /** @return array{from: string, to: string, diff: string, percent: float|null} */
    private function diffTotal(string $from, string $to): array
    {
        $fromValue = (float) $from;
        $toValue = (float) $to;
        $diff = $toValue - $fromValue;

        return [
            'from' => $from,
            'to' => $to,
            'diff' => number_format($diff, 2, '.', ''),
            'percent' => abs($fromValue) > 0.005 ? round($diff / abs($fromValue) * 100, 1) : null,
        ];
    }

    private function statementOfEquityData(string $from, string $to, ?int $fiscalYearId = null): array
    {
        $beginningDate = Carbon::parse($from)->subDay()->toDateString();
        $beginning = $this->balanceSheetData($beginningDate, $fiscalYearId);
        $ending = $this->balanceSheetData($to, $fiscalYearId);
        $income = $this->incomeStatementData($from, $to);

        $movements = DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)->where('a.type', 'equity')
            ->whereBetween('e.date', [$from, $to])
            ->select('a.id as account_id', 'a.code', 'a.name',
                DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('a.id', 'a.code', 'a.name')->orderBy('a.code')->get()
            ->map(function ($row) {
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;

                return [
                    'account_id' => $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'contributions' => number_format($credit, 2, '.', ''),
                    'withdrawals' => number_format($debit, 2, '.', ''),
                    'net' => number_format($credit - $debit, 2, '.', ''),
                ];
            });

        $totalContributions = $movements->sum(fn ($r) => (float) $r['contributions']);
        $totalWithdrawals = $movements->sum(fn ($r) => (float) $r['withdrawals']);
        $netMovement = $totalContributions - $totalWithdrawals;

        $beginningBalance = (float) $beginning['total_equity_net'];
        $netIncome = (float) $income['net_profit'];
        $endingBalance = $beginningBalance + $netIncome + $netMovement;
        $endingBalanceRef = (float) $ending['total_equity_net'];

        return [
            'from' => $from,
            'to' => $to,
            'beginning_balance' => number_format($beginningBalance, 2, '.', ''),
            'net_income' => number_format($netIncome, 2, '.', ''),
            'is_profit' => $netIncome >= 0,
            'movements' => $movements->values()->all(),
            'total_contributions' => number_format($totalContributions, 2, '.', ''),
            'total_withdrawals' => number_format($totalWithdrawals, 2, '.', ''),
            'net_movement' => number_format($netMovement, 2, '.', ''),
            'ending_balance' => number_format($endingBalance, 2, '.', ''),
            'matches_balance_sheet' => abs($endingBalance - $endingBalanceRef) < 0.005,
        ];
    }
}
