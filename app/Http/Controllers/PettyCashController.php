<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PettyCashTransaction;
use App\Models\Setting;
use App\Services\FirebaseStorageService;
use App\Services\FirestoreApprovalService;
use App\Services\PdfReport;
use App\Services\PettyCashApprovalService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PettyCashController extends Controller
{
    /**
     * GET /petty-cash/transactions — paginates when `per_page` is given (the main
     * grid); otherwise returns the full matching set (the spreadsheet/PDF exports,
     * which need every row in the date range, not just one page).
     */
    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'in:expense,replenishment'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'source_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = PettyCashTransaction::with(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'sourceAccount:id,code,name', 'creditLines.sourceAccount:id,code,name', 'createdBy:id,name'])
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from, fn ($q) => $q->where('date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('date', '<=', $request->to))
            ->when($request->source_account_id, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('source_account_id', $request->source_account_id)
                ->orWhereHas('creditLines', fn ($q3) => $q3->where('source_account_id', $request->source_account_id))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->string('search'));
                $q->where(function ($q) use ($term) {
                    $q->where('beneficiary_name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('amount', 'like', "%{$term}%")
                        ->orWhereHas('contraAccount', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('per_page')) {
            $expenseTotal = (clone $query)->reorder()->where('type', 'expense')->sum('amount');

            return response()->json([
                ...$query->paginate($request->integer('per_page'))->withQueryString()->toArray(),
                'totals' => [
                    'expense' => (float) $expenseTotal,
                ],
            ]);
        }

        return response()->json($query->get());
    }

    /**
     * POST /petty-cash/reconcile-pending — catch up MySQL for every still-pending
     * expense against its Firestore mirror doc (approvals/receipts made via
     * WhatsApp while nobody had the app open). Moved out of the list endpoint
     * (which used to do this on every page load) so loading the page stays fast;
     * the button that calls this lets the user pull the same catch-up on demand.
     */
    public function reconcilePending(PettyCashApprovalService $approval): JsonResponse
    {
        $pending = PettyCashTransaction::where('type', 'expense')
            ->whereNull('manager_approved_at')
            ->get();

        $reconciledCount = 0;

        foreach ($pending as $t) {
            try {
                $before = $t->manager_approved_at;
                $t = $approval->reconcileFromFirestore($t);
                if ($t->manager_approved_at !== $before) {
                    $reconciledCount++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to reconcile petty cash approval from Firestore', [
                    'transaction_id' => $t->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'checked' => $pending->count(),
            'reconciled' => $reconciledCount,
            'message' => $reconciledCount > 0
                ? "تمت مزامنة {$reconciledCount} من أصل {$pending->count()} مصروف معلّق."
                : 'لا توجد اعتمادات جديدة عبر واتساب.',
        ]);
    }

    /** POST /petty-cash/sync-expense-accounts — re-push the active expense account list to Firestore for the WhatsApp "new expense" flow. */
    public function syncExpenseAccounts(FirestoreApprovalService $firestore): JsonResponse
    {
        $firestore->syncExpenseAccounts();

        return response()->json(['message' => 'تم مزامنة قائمة الحسابات مع واتساب.']);
    }

    /**
     * POST /petty-cash/import-whatsapp-requests — manually pull in any pending
     * "new expense" requests submitted via the WhatsApp Flow, instead of waiting
     * for the next transactions list load to import them automatically.
     */
    public function importWhatsAppRequests(PettyCashApprovalService $approval): JsonResponse
    {
        $count = $approval->importPendingWhatsAppRequests();

        return response()->json([
            'imported' => $count,
            'message' => $count > 0 ? "تم استيراد {$count} طلب من واتساب." : 'لا توجد طلبات جديدة من واتساب.',
        ]);
    }

    public function transactionsPdf(Request $request): Response
    {
        $request->validate([
            'type' => ['nullable', 'in:expense,replenishment'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'source_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $transactions = PettyCashTransaction::with(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'sourceAccount:id,code,name', 'creditLines.sourceAccount:id,code,name'])
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from, fn ($q) => $q->where('date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('date', '<=', $request->to))
            ->when($request->source_account_id, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('source_account_id', $request->source_account_id)
                ->orWhereHas('creditLines', fn ($q3) => $q3->where('source_account_id', $request->source_account_id))))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $typeLabels = ['expense' => 'مصروف', 'replenishment' => 'تغذية'];

        $parts = [];
        if ($request->from && $request->to) {
            $parts[] = "الفترة من {$request->from} إلى {$request->to}";
        } elseif ($request->from) {
            $parts[] = "من {$request->from}";
        } elseif ($request->to) {
            $parts[] = "إلى {$request->to}";
        }
        if ($request->type) {
            $parts[] = $typeLabels[$request->type];
        }
        $subtitle = implode('   |   ', $parts) ?: 'جميع الحركات';

        $pdf = PdfReport::make('حركات صندوق النثريات', $subtitle);

        // Type, Date, Beneficiary, Contra account, Source account, Description, Amount — total = 190
        $cols = [14, 20, 26, 32, 32, 46, 20];
        $pdf->tableHead(['النوع', 'التاريخ', 'المستفيد', 'الحساب المقابل', 'الحساب الدائن', 'البيان', 'المبلغ'], $cols);

        $totalExpense = 0.0;
        $totalReplenishment = 0.0;
        $odd = false;

        foreach ($transactions as $t) {
            $pdf->SetFillColor($odd ? 249 : 255, $odd ? 250 : 255, $odd ? 251 : 255);
            $pdf->Cell($cols[0], 7, $typeLabels[$t->type], 1, 0, 'C', true);
            $pdf->Cell($cols[1], 7, $t->date->format('Y-m-d'), 1, 0, 'C', true);
            $pdf->Cell($cols[2], 7, $pdf->fit($t->beneficiary_name ?? '—', $cols[2] - 2), 1, 0, 'R', true);
            $pdf->Cell($cols[3], 7, $pdf->fit($this->contraAccountLabel($t), $cols[3] - 2), 1, 0, 'R', true);
            $pdf->Cell($cols[4], 7, $pdf->fit($this->sourceAccountLabel($t), $cols[4] - 2), 1, 0, 'R', true);
            $pdf->Cell($cols[5], 7, $pdf->fit($t->description ?? '—', $cols[5] - 2), 1, 0, 'R', true);
            $pdf->Cell($cols[6], 7, PdfReport::n($t->amount), 1, 1, 'C', true);

            if ($t->type === 'expense') {
                $totalExpense += (float) $t->amount;
            } else {
                $totalReplenishment += (float) $t->amount;
            }
            $odd = ! $odd;
        }

        $labelW = $cols[0] + $cols[1] + $cols[2] + $cols[3] + $cols[4] + $cols[5];
        $pdf->totalsRow(['إجمالي المصروفات', PdfReport::n($totalExpense)], [$labelW, $cols[6]]);
        $pdf->totalsRow(['إجمالي التغذية', PdfReport::n($totalReplenishment)], [$labelW, $cols[6]]);
        $pdf->totalsRow(['صافي التغيير', PdfReport::n($totalReplenishment - $totalExpense)], [$labelW, $cols[6]]);

        return $pdf->respond('petty-cash-transactions.pdf');
    }

    public function transactionsExcel(Request $request): StreamedResponse
    {
        $request->validate([
            'type' => ['nullable', 'in:expense,replenishment'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'source_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $transactions = PettyCashTransaction::with(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'sourceAccount:id,code,name', 'creditLines.sourceAccount:id,code,name'])
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from, fn ($q) => $q->where('date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('date', '<=', $request->to))
            ->when($request->source_account_id, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('source_account_id', $request->source_account_id)
                ->orWhereHas('creditLines', fn ($q3) => $q3->where('source_account_id', $request->source_account_id))))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $typeLabels = ['expense' => 'مصروف', 'replenishment' => 'تغذية'];
        $headers = ['التاريخ', 'النوع', 'المستفيد', 'الحساب المدين', 'الحساب الدائن', 'البيان', 'حالة الاعتماد', 'تاريخ الاعتماد', 'المبلغ'];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('حركات صندوق النثريات');
        $sheet->setRightToLeft(true);
        $sheet->fromArray($headers, null, 'A1');

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1565C0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        $totalExpense = 0.0;
        $totalReplenishment = 0.0;

        foreach ($transactions as $t) {
            $statusLabel = $t->type === 'expense'
                ? ($t->status === 'approved' ? 'معتمد' : 'بانتظار اعتماد المدير')
                : '';

            $sheet->fromArray([
                $t->date->format('Y-m-d'),
                $typeLabels[$t->type],
                $t->beneficiary_name ?? '',
                $this->contraAccountLabel($t),
                $this->sourceAccountLabel($t),
                $t->description ?? '',
                $statusLabel,
                $t->manager_approved_at?->format('Y-m-d H:i') ?? '',
                (float) $t->amount,
            ], null, "A{$row}");

            if ($t->type === 'expense') {
                $totalExpense += (float) $t->amount;
            } else {
                $totalReplenishment += (float) $t->amount;
            }
            $row++;
        }

        $lastDataRow = $row - 1;
        if ($lastDataRow >= 2) {
            $sheet->getStyle("A2:{$lastCol}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:I{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $row++;
        $firstTotalsRow = $row;
        $sheet->setCellValue("H{$row}", 'إجمالي المصروفات');
        $sheet->setCellValue("I{$row}", $totalExpense);
        $row++;
        $sheet->setCellValue("H{$row}", 'إجمالي التغذية');
        $sheet->setCellValue("I{$row}", $totalReplenishment);
        $row++;
        $sheet->setCellValue("H{$row}", 'صافي التغيير');
        $sheet->setCellValue("I{$row}", $totalReplenishment - $totalExpense);

        $sheet->getStyle("H{$firstTotalsRow}:I{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        $sheet->getStyle("I{$firstTotalsRow}:I{$row}")->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'petty-cash-transactions.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function storeExpense(Request $request, FirestoreApprovalService $firestore, FirebaseStorageService $storage, WhatsAppService $whatsapp): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'amount' => ['required_without:lines', 'nullable', 'numeric', 'min:0.01'],
            'contra_account_id' => ['required_without:lines', 'nullable', 'integer', 'exists:accounts,id'],
            'lines' => ['nullable', 'array', 'min:2'],
            'lines.*.contra_account_id' => ['required_with:lines', 'integer', 'exists:accounts,id'],
            'lines.*.amount' => ['required_with:lines', 'numeric', 'min:0.01'],
            'source_account_id' => ['required_without:credit_lines', 'nullable', 'integer', 'exists:accounts,id'],
            'credit_lines' => ['nullable', 'array', 'min:2'],
            'credit_lines.*.source_account_id' => ['required_with:credit_lines', 'integer', 'exists:accounts,id'],
            'credit_lines.*.amount' => ['required_with:credit_lines', 'numeric', 'min:0.01'],
        ]);

        if (FiscalYear::isDateLocked($data['date'])) {
            abort(422, 'هذه الفترة مغلقة ولا يمكن إضافة مصروف لها.');
        }

        $isCompound = ! empty($data['lines']);
        $amount = $isCompound ? collect($data['lines'])->sum('amount') : $data['amount'];

        $isCompoundCredit = ! empty($data['credit_lines']);
        if ($isCompoundCredit && abs(collect($data['credit_lines'])->sum('amount') - $amount) > 0.005) {
            abort(422, 'مجموع حسابات التمويل يجب أن يساوي إجمالي المصروف');
        }

        $documentPath = null;
        $documentOriginalName = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('petty-cash-receipts', 'local');
            $documentOriginalName = $request->file('document')->getClientOriginalName();
        }

        $transaction = DB::transaction(function () use ($request, $data, $isCompound, $isCompoundCredit, $amount, $documentPath, $documentOriginalName) {
            $transaction = PettyCashTransaction::create([
                'source_account_id' => $isCompoundCredit ? null : $data['source_account_id'],
                'created_by_user_id' => $request->user()->id,
                'type' => 'expense',
                'status' => 'pending',
                'date' => $data['date'],
                'amount' => $amount,
                'beneficiary_name' => $data['beneficiary_name'] ?? null,
                'contra_account_id' => $isCompound ? null : $data['contra_account_id'],
                'description' => $data['description'] ?? null,
                'document_path' => $documentPath,
                'document_original_name' => $documentOriginalName,
                'journal_entry_id' => null,
            ]);

            if ($isCompound) {
                $transaction->lines()->createMany($data['lines']);
            }

            if ($isCompoundCredit) {
                $transaction->creditLines()->createMany($data['credit_lines']);
            }

            return $transaction;
        });

        [$documentUrl, $documentType] = $this->uploadReceiptToFirebaseStorage($storage, $transaction);
        if ($documentUrl) {
            $transaction->update(['document_firebase_url' => $documentUrl]);
        }

        try {
            $firestore->createMirror($transaction, $documentUrl, $documentType);
        } catch (\Throwable $e) {
            Log::error('Failed to create Firestore mirror for petty cash transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        $notifyRaw = Setting::where('key', 'petty_cash_notify_on_create')->value('value');
        $notifyOnCreate = $notifyRaw === null || $notifyRaw === '' ? true : filter_var($notifyRaw, FILTER_VALIDATE_BOOLEAN);

        $notifications = $notifyOnCreate ? $whatsapp->sendApprovalNotifications($transaction) : [];

        return response()->json([
            ...$transaction->load(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'sourceAccount:id,code,name', 'creditLines.sourceAccount:id,code,name'])->toArray(),
            'notifications' => $notifications,
        ], 201);
    }

    /**
     * (Re)send the WhatsApp approval-request template to the designated manager
     * for a pending expense. Synchronous — the caller waits for the send to
     * complete (or fail) and shows the result.
     */
    public function sendNotification(PettyCashTransaction $pettyCashTransaction, WhatsAppService $whatsapp): JsonResponse
    {
        abort_if($pettyCashTransaction->type !== 'expense', 422, 'لا يخضع هذا النوع من الحركات للاعتماد.');
        abort_if($pettyCashTransaction->status !== 'pending', 422, 'تم اعتماد هذا المصروف بالفعل.');

        return response()->json([
            'notifications' => $whatsapp->sendApprovalNotifications($pettyCashTransaction),
        ]);
    }

    public function destroy(PettyCashTransaction $pettyCashTransaction, FirestoreApprovalService $firestore): JsonResponse
    {
        if (FiscalYear::isDateLocked($pettyCashTransaction->date->toDateString())) {
            abort(422, 'هذه الفترة مغلقة ولا يمكن الحذف.');
        }

        DB::transaction(function () use ($pettyCashTransaction) {
            $entryId = $pettyCashTransaction->journal_entry_id;
            $documentPath = $pettyCashTransaction->document_path;

            $pettyCashTransaction->delete();

            if ($entryId) {
                JournalEntry::find($entryId)?->delete();
            }
            if ($documentPath) {
                Storage::disk('local')->delete($documentPath);
            }
        });

        try {
            $firestore->delete($pettyCashTransaction->id);
        } catch (\Throwable $e) {
            Log::error('Failed to delete Firestore mirror for petty cash transaction', [
                'transaction_id' => $pettyCashTransaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(null, 204);
    }

    public function document(PettyCashTransaction $pettyCashTransaction): BinaryFileResponse
    {
        abort_if(! $pettyCashTransaction->document_path, 404, 'لا يوجد مستند مرفق.');
        abort_if(! Storage::disk('local')->exists($pettyCashTransaction->document_path), 404, 'الملف غير موجود.');

        return response()->download(
            Storage::disk('local')->path($pettyCashTransaction->document_path),
            $pettyCashTransaction->document_original_name ?? 'receipt'
        );
    }

    /**
     * Catch up MySQL for one transaction against its Firestore mirror doc —
     * called by the frontend's real-time listener the moment a WhatsApp-tap
     * approval lands in Firestore, rather than waiting on the next full list reload.
     */
    public function reconcile(PettyCashTransaction $pettyCashTransaction, PettyCashApprovalService $service): JsonResponse
    {
        $transaction = $service->reconcileFromFirestore($pettyCashTransaction);

        return response()->json($transaction->load(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'creditLines.sourceAccount:id,code,name', 'managerApprovedBy:id,name']));
    }

    public function approveByManager(Request $request, PettyCashTransaction $pettyCashTransaction, PettyCashApprovalService $service): JsonResponse
    {
        $managerId = (int) Setting::where('key', 'petty_cash_manager_user_id')->value('value');
        abort_if(! $managerId || $request->user()->id !== $managerId, 403, 'غير مصرح لك باعتماد هذا المصروف.');

        return response()->json($service->approve($pettyCashTransaction, $managerId));
    }

    public function uploadDocument(Request $request, PettyCashTransaction $pettyCashTransaction, FirebaseStorageService $storage, FirestoreApprovalService $firestore): JsonResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($pettyCashTransaction->document_path) {
            Storage::disk('local')->delete($pettyCashTransaction->document_path);
        }

        $pettyCashTransaction->update([
            'document_path' => $request->file('document')->store('petty-cash-receipts', 'local'),
            'document_original_name' => $request->file('document')->getClientOriginalName(),
        ]);

        [$documentUrl, $documentType] = $this->uploadReceiptToFirebaseStorage($storage, $pettyCashTransaction);

        if ($documentUrl) {
            $pettyCashTransaction->update(['document_firebase_url' => $documentUrl]);

            try {
                $firestore->updateDocument($pettyCashTransaction->id, $documentUrl, $documentType);
            } catch (\Throwable $e) {
                Log::error('Failed to update Firestore mirror document link for petty cash transaction', [
                    'transaction_id' => $pettyCashTransaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($pettyCashTransaction->load(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'sourceAccount:id,code,name', 'creditLines.sourceAccount:id,code,name']));
    }

    /** DELETE /petty-cash/transactions/{id}/document — remove the attached receipt without replacing it. */
    public function deleteDocument(PettyCashTransaction $pettyCashTransaction, FirestoreApprovalService $firestore): JsonResponse
    {
        abort_if(! $pettyCashTransaction->document_path, 404, 'لا يوجد مستند مرفق.');

        Storage::disk('local')->delete($pettyCashTransaction->document_path);

        $pettyCashTransaction->update([
            'document_path' => null,
            'document_original_name' => null,
            'document_firebase_url' => null,
        ]);

        try {
            $firestore->clearDocument($pettyCashTransaction->id);
        } catch (\Throwable $e) {
            Log::error('Failed to clear Firestore mirror document link for petty cash transaction', [
                'transaction_id' => $pettyCashTransaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json($pettyCashTransaction->load(['contraAccount:id,code,name', 'lines.contraAccount:id,code,name', 'sourceAccount:id,code,name', 'creditLines.sourceAccount:id,code,name']));
    }

    /** A compound transaction has no single contra account — join its lines' account names instead. */
    private function contraAccountLabel(PettyCashTransaction $t): string
    {
        if ($t->contraAccount) {
            return $t->contraAccount->name;
        }

        return $t->lines->pluck('contraAccount.name')->filter()->implode('، ');
    }

    /** A compound-credit transaction has no single source account — join its credit lines' account names instead. */
    private function sourceAccountLabel(PettyCashTransaction $t): string
    {
        if ($t->sourceAccount) {
            return $t->sourceAccount->name;
        }

        return $t->creditLines->pluck('sourceAccount.name')->filter()->implode('، ');
    }

    /**
     * Pushes the transaction's already-stored local receipt to Firebase Storage
     * so the WhatsApp "عرض المستند" button can be answered with a plain link.
     * Best-effort — a failure here shouldn't break expense creation/upload, it
     * just means that one button won't work until the next successful attempt.
     *
     * @return array{0: ?string, 1: ?string} [documentUrl, documentType]
     */
    private function uploadReceiptToFirebaseStorage(FirebaseStorageService $storage, PettyCashTransaction $transaction): array
    {
        if (! $transaction->document_path || ! Storage::disk('local')->exists($transaction->document_path)) {
            return [null, null];
        }

        $mimeType = Storage::disk('local')->mimeType($transaction->document_path) ?: 'application/octet-stream';
        $type = str_starts_with($mimeType, 'image/') ? 'image' : 'document';
        $ext = pathinfo($transaction->document_path, PATHINFO_EXTENSION) ?: 'bin';
        $objectName = "petty-cash-receipts/{$transaction->id}.{$ext}";

        try {
            $url = $storage->upload(Storage::disk('local')->get($transaction->document_path), $objectName, $mimeType);

            return $url ? [$url, $type] : [null, null];
        } catch (\Throwable $e) {
            Log::error('Failed to upload petty cash receipt to Firebase Storage', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [null, null];
        }
    }
}
