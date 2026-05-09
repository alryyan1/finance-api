<?php

namespace App\Http\Controllers;

use App\Models\CashVoucher;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Services\PdfReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CashVoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'in:receipt,payment'],
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $vouchers = CashVoucher::with([
            'cashAccount:id,code,name',
            'contraAccount:id,code,name',
            'party:id,name',
        ])
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from, fn ($q) => $q->where('date', '>=', $request->from))
            ->when($request->to,   fn ($q) => $q->where('date', '<=', $request->to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json($vouchers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'              => ['required', 'in:receipt,payment'],
            'date'              => ['required', 'date'],
            'reference'         => ['nullable', 'string', 'max:50'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'    => ['required', 'in:cash,bank_transfer,check'],
            'cash_account_id'   => ['required', 'integer', 'exists:accounts,id'],
            'contra_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:cash_account_id'],
            'party_id'          => ['nullable', 'integer', 'exists:parties,id'],
            'description'       => ['nullable', 'string', 'max:500'],
        ]);

        if (FiscalYear::isDateLocked($data['date'])) {
            abort(422, 'هذه الفترة مغلقة ولا يمكن إضافة إذن لها.');
        }

        return DB::transaction(function () use ($data) {
            $typeLabel = $data['type'] === 'receipt' ? 'إذن قبض' : 'إذن صرف';
            $desc = $data['description'] ?? $typeLabel;

            // Receipt → Dr cash, Cr contra | Payment → Dr contra, Cr cash
            [$drAcct, $crAcct] = $data['type'] === 'receipt'
                ? [$data['cash_account_id'],   $data['contra_account_id']]
                : [$data['contra_account_id'], $data['cash_account_id']];

            $entry = JournalEntry::create([
                'date'        => $data['date'],
                'reference'   => $data['reference'],
                'description' => $desc,
                'is_posted'   => true,
            ]);

            $entry->lines()->createMany([
                [
                    'account_id'  => $drAcct,
                    'party_id'    => $data['party_id'] ?? null,
                    'debit'       => $data['amount'],
                    'credit'      => 0,
                    'description' => $desc,
                ],
                [
                    'account_id'  => $crAcct,
                    'party_id'    => $data['party_id'] ?? null,
                    'debit'       => 0,
                    'credit'      => $data['amount'],
                    'description' => $desc,
                ],
            ]);

            $voucher = CashVoucher::create([...$data, 'journal_entry_id' => $entry->id]);

            return response()->json(
                $voucher->load(['cashAccount:id,code,name', 'contraAccount:id,code,name', 'party:id,name']),
                201
            );
        });
    }

    public function destroy(CashVoucher $cashVoucher): JsonResponse
    {
        if (FiscalYear::isDateLocked($cashVoucher->date->toDateString())) {
            abort(422, 'هذه الفترة مغلقة ولا يمكن الحذف.');
        }

        DB::transaction(function () use ($cashVoucher) {
            $entryId = $cashVoucher->journal_entry_id;
            $cashVoucher->delete();
            if ($entryId) {
                JournalEntry::find($entryId)?->delete();
            }
        });

        return response()->json(null, 204);
    }

    public function voucher(CashVoucher $cashVoucher): Response
    {
        $cashVoucher->load(['cashAccount', 'contraAccount', 'party']);

        $typeAr = $cashVoucher->type === 'receipt' ? 'إذن قبض' : 'إذن صرف';
        $methodAr = match ($cashVoucher->payment_method) {
            'cash'          => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'check'         => 'شيك',
            default         => $cashVoucher->payment_method,
        };

        $pdf = PdfReport::make($typeAr, 'رقم: ' . ($cashVoucher->reference ?? $cashVoucher->id));
        $pdf->SetFont('dejavusans', '', 10);

        $pdf->SetFillColor(245, 247, 250);
        $pdf->SetDrawColor(150, 150, 150);

        // Date + reference row
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(90, 10, 'التاريخ: ' . $cashVoucher->date->format('Y/m/d'), 1, 0, 'R', true);
        $pdf->Cell(10, 10, '', 0, 0);
        $pdf->Cell(90, 10, 'رقم الإذن: ' . ($cashVoucher->reference ?? $cashVoucher->id), 1, 1, 'R', true);
        $pdf->Ln(3);

        // Party row
        $partyName = $cashVoucher->party?->name ?? '—';
        $fromLabel = $cashVoucher->type === 'receipt' ? 'استلمنا من السيد / الجهة:' : 'صرفنا إلى السيد / الجهة:';
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(190, 10, $fromLabel . '   ' . $partyName, 1, 1, 'R', true);
        $pdf->Ln(3);

        // Amount row (highlighted)
        $amount = number_format((float) $cashVoucher->amount, 2);
        $pdf->SetFont('dejavusans', 'B', 13);
        $pdf->SetFillColor(254, 252, 232);
        $pdf->Cell(190, 12, 'مبلغ وقدره:   ' . $amount, 1, 1, 'R', true);
        $pdf->Ln(3);

        // Description row
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->Cell(190, 10, 'وذلك عن:   ' . ($cashVoucher->description ?? '—'), 1, 1, 'R', true);
        $pdf->Ln(3);

        // Payment method + account
        $pdf->Cell(90, 10, 'الحساب النقدي: ' . $cashVoucher->cashAccount->name, 1, 0, 'R', true);
        $pdf->Cell(10, 10, '', 0, 0);
        $pdf->Cell(90, 10, 'طريقة الدفع: ' . $methodAr, 1, 1, 'R', true);
        $pdf->Ln(3);

        // Contra account
        $pdf->Cell(190, 10, 'الحساب المقابل: ' . $cashVoucher->contraAccount->name, 1, 1, 'R', true);
        $pdf->Ln(10);

        // Signature boxes
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(58, 9, 'المدير المالي', 1, 0, 'C');
        $pdf->Cell(4, 9, '', 0, 0);
        $pdf->Cell(58, 9, 'أمين الصندوق', 1, 0, 'C');
        $pdf->Cell(4, 9, '', 0, 0);
        $pdf->Cell(58, 9, ($cashVoucher->type === 'receipt' ? 'المستلم' : 'المُسلَّم إليه') . ' / التوقيع', 1, 1, 'C');

        $pdf->Cell(58, 18, '', 1, 0, 'C');
        $pdf->Cell(4,  18, '', 0, 0);
        $pdf->Cell(58, 18, '', 1, 0, 'C');
        $pdf->Cell(4,  18, '', 0, 0);
        $pdf->Cell(58, 18, '', 1, 1, 'C');

        $filename = ($cashVoucher->type === 'receipt' ? 'receipt' : 'payment') . '-voucher-' . $cashVoucher->id . '.pdf';
        return $pdf->respond($filename);
    }
}
