<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PettyCashTransaction;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PettyCashApprovalService
{
    public function __construct(
        private readonly FirestoreApprovalService $firestore,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * Record the manager's approval for a pending petty cash expense — this is
     * what posts the journal entry. Idempotent — re-approving an already
     * approved transaction is a no-op, so redelivered webhooks are safe.
     */
    public function approve(PettyCashTransaction $transaction, int $approverUserId): PettyCashTransaction
    {
        abort_if($transaction->type !== 'expense', 422, 'لا يخضع هذا النوع من الحركات للاعتماد.');

        $wasNewApproval = false;

        $transaction = DB::transaction(function () use ($transaction, $approverUserId, &$wasNewApproval) {
            $transaction = PettyCashTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($transaction->manager_approved_at !== null) {
                return $transaction->load(['contraAccount:id,code,name', 'managerApprovedBy:id,name']);
            }

            $transaction->update(['manager_approved_at' => now(), 'manager_approved_by_user_id' => $approverUserId]);
            $wasNewApproval = true;

            $transaction->refresh();
            $this->postJournalEntry($transaction);

            return $transaction->fresh(['contraAccount:id,code,name', 'managerApprovedBy:id,name']);
        });

        // Firestore is a best-effort mirror, not the source of truth — never let it
        // fail the approval itself (e.g. when Firebase credentials aren't configured).
        if ($wasNewApproval) {
            try {
                $this->firestore->markApproved($transaction->id);
            } catch (\Throwable $e) {
                Log::error('Failed to sync petty cash approval to Firestore', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $transaction;
    }

    /**
     * Catches up MySQL with anything that happened via WhatsApp while nobody had
     * the app open: an approval tap (the now-removed real-time webhook used to
     * push these), or a receipt photo/PDF attached through the bot's list-picker
     * flow, which uploads straight to Firebase Storage and updates the Firestore
     * mirror doc directly. Called for each expense whenever the transactions list
     * loads — cheap, since it's a single Firestore read, and a no-op the instant
     * nothing on the mirror doc is new.
     *
     * Not gated on transaction status: a receipt attached via WhatsApp after the
     * manager already approved must still sync, so this keeps checking even
     * once status is "approved".
     */
    public function reconcileFromFirestore(PettyCashTransaction $transaction): PettyCashTransaction
    {

        $doc = $this->firestore->fetch($transaction->id);
        if (! $doc) {
            return $transaction;
        }

        if (($doc['manager_approved'] ?? false) && ! $transaction->manager_approved_at) {
            $managerId = (int) Setting::where('key', 'petty_cash_manager_user_id')->value('value');
            if ($managerId) {
                $transaction = $this->approve($transaction, $managerId);
            }
        }

        $documentUrl = $doc['document_url'] ?? null;
        if ($documentUrl && $documentUrl !== $transaction->document_firebase_url) {
            $transaction = $this->syncDocumentFromFirestore($transaction, $documentUrl, $doc['document_type'] ?? null);
        }

        return $transaction;
    }

    /**
     * Pulls a receipt attached via the WhatsApp bot (uploaded straight to Firebase
     * Storage, never touching Laravel) down into local storage, so it behaves
     * exactly like one uploaded through the app — downloadable, viewable, and
     * replaceable from the Petty Cash page. Best-effort: a failure here (e.g. the
     * download URL has expired) leaves the transaction's existing document alone.
     */
    private function syncDocumentFromFirestore(PettyCashTransaction $transaction, string $documentUrl, ?string $documentType): PettyCashTransaction
    {
        try {
            $response = Http::get($documentUrl);
            if (! $response->successful()) {
                return $transaction;
            }

            if ($transaction->document_path) {
                Storage::disk('local')->delete($transaction->document_path);
            }

            $ext = $documentType === 'image' ? 'jpg' : 'pdf';
            $path = 'petty-cash-receipts/'.$transaction->id.'-whatsapp-'.Str::random(8).'.'.$ext;
            Storage::disk('local')->put($path, $response->body());

            $transaction->update([
                'document_path' => $path,
                'document_original_name' => $documentType === 'image' ? 'واتساب.jpg' : 'واتساب.pdf',
                'document_firebase_url' => $documentUrl,
            ]);

            return $transaction->fresh();
        } catch (\Throwable $e) {
            Log::error('Failed to sync WhatsApp-attached petty cash receipt from Firestore', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return $transaction;
        }
    }

    /**
     * Imports "new expense" requests submitted through the WhatsApp Flow
     * (petty_cash_new_expense_request, triggered by texting "إذن جديد") as
     * real, pending petty cash transactions — the same effect as submitting
     * the in-app "مصروف جديد" form, including the usual approval
     * notifications. Runs automatically whenever the transactions list loads.
     * Best-effort per request: one that fails validation is dropped (its
     * Firestore doc removed) and the submitter is told why over WhatsApp,
     * rather than blocking every subsequent list load retrying it forever.
     *
     * @return int number of pending requests found and processed
     */
    public function importPendingWhatsAppRequests(): int
    {
        $requests = $this->firestore->fetchPendingWhatsAppRequests();

        foreach ($requests as $request) {
            $this->importOneWhatsAppRequest($request);
        }

        return count($requests);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function importOneWhatsAppRequest(array $request): void
    {
        $requestId = (string) ($request['id'] ?? '');
        $phone = (string) ($request['submitted_by_phone'] ?? '');

        try {
            $sourceAccountId = (int) Setting::where('key', 'petty_cash_bank_account_id')->value('value');
            if (! $sourceAccountId) {
                throw new \RuntimeException('لم يتم إعداد حساب البنك لصندوق النثريات بعد.');
            }

            $amount = (float) ($request['amount'] ?? 0);
            if ($amount <= 0) {
                throw new \RuntimeException('المبلغ غير صالح.');
            }

            $contraAccount = Account::where('id', (int) ($request['contra_account_id'] ?? 0))
                ->where('type', 'expense')
                ->first();
            if (! $contraAccount) {
                throw new \RuntimeException('حساب المصروف غير صالح.');
            }

            $date = now()->toDateString();
            if (FiscalYear::isDateLocked($date)) {
                throw new \RuntimeException('الفترة المالية الحالية مغلقة.');
            }

            $transaction = PettyCashTransaction::create([
                'source_account_id' => $sourceAccountId,
                'type' => 'expense',
                'status' => 'pending',
                'date' => $date,
                'amount' => $amount,
                'beneficiary_name' => ($request['beneficiary_name'] ?? null) ?: null,
                'contra_account_id' => $contraAccount->id,
                'description' => ($request['description'] ?? null) ?: null,
                'journal_entry_id' => null,
            ]);

            $this->firestore->createMirror($transaction);
            $this->whatsapp->sendApprovalNotifications($transaction);

            if ($phone !== '') {
                $this->whatsapp->sendText($phone, "تم إنشاء طلب الصرف رقم #{$transaction->id} بمبلغ ".number_format($amount, 2).' بانتظار اعتماد المدير.');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to import WhatsApp petty cash request', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            if ($phone !== '') {
                try {
                    $this->whatsapp->sendText($phone, 'تعذّر إنشاء طلب الصرف: '.$e->getMessage());
                } catch (\Throwable) {
                    // best-effort
                }
            }
        } finally {
            if ($requestId !== '') {
                try {
                    $this->firestore->deletePendingWhatsAppRequest($requestId);
                } catch (\Throwable $e) {
                    Log::error('Failed to delete imported WhatsApp petty cash request from Firestore', [
                        'request_id' => $requestId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function postJournalEntry(PettyCashTransaction $transaction): void
    {
        $desc = $transaction->description ?? 'مصروف نثرية';

        $entry = JournalEntry::create([
            'date' => $transaction->date,
            'description' => $desc,
            'is_posted' => true,
        ]);

        $entry->lines()->createMany([
            [
                'account_id' => $transaction->contra_account_id,
                'debit' => $transaction->amount,
                'credit' => 0,
                'description' => $desc,
            ],
            [
                'account_id' => $transaction->source_account_id,
                'debit' => 0,
                'credit' => $transaction->amount,
                'description' => $desc,
            ],
        ]);

        $transaction->update(['journal_entry_id' => $entry->id, 'status' => 'approved']);
    }
}
