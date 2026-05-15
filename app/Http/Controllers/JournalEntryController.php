<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Services\PdfReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $this->assertNotLocked($data['date']);

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
        $this->assertNotLocked($data['date']);

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
        $this->assertNotLocked($journalEntry->date->toDateString());

        $journalEntry->lines()->delete();
        $journalEntry->delete();

        return response()->json(null, 204);
    }

    public function post(JournalEntry $journalEntry): JsonResponse
    {
        $this->assertNotLocked($journalEntry->date->toDateString());
        $journalEntry->update(['is_posted' => ! $journalEntry->is_posted]);

        return response()->json($journalEntry->fresh());
    }

    public function reverse(JournalEntry $journalEntry): JsonResponse
    {
        if (! $journalEntry->is_posted) {
            return response()->json(['message' => 'يمكن عكس القيود المرحَّلة فقط'], 422);
        }

        if ($journalEntry->reversed_by) {
            return response()->json(['message' => 'تم عكس هذا القيد مسبقاً'], 422);
        }

        $reversal = DB::transaction(function () use ($journalEntry) {
            $reversal = JournalEntry::create([
                'date'        => now()->toDateString(),
                'reference'   => 'REV-' . $journalEntry->id,
                'description' => 'عكس: ' . $journalEntry->description,
                'is_posted'   => true,
                'reversal_of' => $journalEntry->id,
            ]);

            foreach ($journalEntry->lines as $line) {
                $reversal->lines()->create([
                    'account_id'  => $line->account_id,
                    'party_id'    => $line->party_id,
                    'description' => $line->description,
                    'debit'       => $line->credit,   // swap
                    'credit'      => $line->debit,    // swap
                ]);
            }

            $journalEntry->update(['reversed_by' => $reversal->id]);

            return $reversal;
        });

        return response()->json(
            $reversal->load('lines.account:id,code,name', 'lines.party:id,name'),
            201
        );
    }

    public function voucher(JournalEntry $journalEntry): Response
    {
        $entry = $journalEntry->load('lines.account:id,code,name', 'lines.party:id,name');

        $status = $entry->is_posted ? 'مرحَّل' : 'مسودة';
        $pdf    = PdfReport::make(
            'سند قيد يومي',
            "رقم: {$entry->id}   |   التاريخ: {$entry->date}   |   الحالة: {$status}"
        );

        // Info box
        $pdf->SetFont('arial', '', 9);
        if ($entry->reference) {
            $pdf->Cell(190, 7, 'المرجع: ' . $entry->reference, 0, 1, 'R');
        }
        $pdf->SetFont('arialbd', '', 10);
        $pdf->Cell(190, 8, 'البيان: ' . $entry->description, 'B', 1, 'R');
        $pdf->Ln(4);

        // Lines table
        $cols   = [10, 20, 70, 45, 45];  // #, Code, Account, Debit, Credit
        $pdf->tableHead(['#', 'الرمز', 'اسم الحساب', 'مدين', 'دائن'], $cols);

        $odd = false;
        foreach ($entry->lines as $i => $line) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $debit  = (float) $line->debit  > 0 ? PdfReport::n($line->debit)  : '—';
            $credit = (float) $line->credit > 0 ? PdfReport::n($line->credit) : '—';
            $pdf->Cell($cols[0], 7, (string) ($i + 1),          1, 0, 'C', true);
            $pdf->Cell($cols[1], 7, $line->account->code,        1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, $line->account->name,        1, 0, 'R', true);
            $pdf->Cell($cols[3], 7, $debit,                      1, 0, 'C', true);
            $pdf->Cell($cols[4], 7, $credit,                     1, 1, 'C', true);
            $odd = !$odd;
        }

        $totalDebit  = $entry->lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $entry->lines->sum(fn ($l) => (float) $l->credit);
        $pdf->totalsRow(
            ['', '', 'الإجمالي', PdfReport::n($totalDebit), PdfReport::n($totalCredit)],
            $cols
        );

        // Signature area
        $pdf->Ln(15);
        $pdf->SetFont('arial', '', 9);
        $pdf->SetDrawColor(150, 150, 150);
        $sig = 58;
        $gap = 16;
        foreach (['المعد', 'المراجع', 'المدير المالي'] as $role) {
            $pdf->Cell($sig, 7, $role, 'B', 0, 'C');
            $pdf->Cell($gap, 7, '', 0, 0);
        }

        return $pdf->respond("voucher-{$entry->id}.pdf");
    }

    private function assertBalanced(array $lines): void
    {
        $debit  = collect($lines)->sum('debit');
        $credit = collect($lines)->sum('credit');

        if (abs($debit - $credit) > 0.005) {
            abort(422, 'مجموع المدين يجب أن يساوي مجموع الدائن');
        }
    }

    private function assertNotLocked(string $date): void
    {
        if (FiscalYear::isDateLocked($date)) {
            abort(422, 'لا يمكن تعديل قيود في سنة مالية مغلقة');
        }
    }
}
