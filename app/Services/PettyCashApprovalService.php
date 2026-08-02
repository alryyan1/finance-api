<?php

namespace App\Services;

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
    public function __construct(private readonly FirestoreApprovalService $firestore) {}

    /**
     * Record one approval (auditor or manager) for a pending petty cash expense.
     * The manager's approval alone posts the journal entry and deducts the fund
     * balance — the auditor's approval is just a review record, it never gates
     * posting. Idempotent — re-approving the same role for an already approved
     * transaction is a no-op, so redelivered webhooks are safe.
     */
    public function approve(PettyCashTransaction $transaction, string $role, int $approverUserId): PettyCashTransaction
    {
        abort_unless(in_array($role, ['auditor', 'manager'], true), 422);
        abort_if($transaction->type !== 'expense', 422, 'لا يخضع هذا النوع من الحركات للاعتماد.');

        $atColumn = "{$role}_approved_at";
        $byColumn = "{$role}_approved_by_user_id";
        $wasNewApproval = false;

        $transaction = DB::transaction(function () use ($transaction, $atColumn, $byColumn, $approverUserId, &$wasNewApproval) {
            $transaction = PettyCashTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($transaction->{$atColumn} !== null) {
                return $transaction->load(['contraAccount:id,code,name', 'auditorApprovedBy:id,name', 'managerApprovedBy:id,name']);
            }

            $transaction->update([$atColumn => now(), $byColumn => $approverUserId]);
            $wasNewApproval = true;
            $transaction->refresh();

            // journal_entry_id guards against double-posting: if the manager already
            // approved earlier, isReadyToPost() stays true forever, so an auditor
            // approval arriving afterward must not re-trigger posting.
            if ($transaction->isReadyToPost() && $transaction->journal_entry_id === null) {
                $this->postAndDeduct($transaction);
            }

            return $transaction->fresh(['contraAccount:id,code,name', 'auditorApprovedBy:id,name', 'managerApprovedBy:id,name']);
        });

        // Firestore is a best-effort mirror, not the source of truth — never let it
        // fail the approval itself (e.g. when Firebase credentials aren't configured).
        if ($wasNewApproval) {
            try {
                $this->firestore->markApproved($transaction->id, $role);
            } catch (\Throwable $e) {
                Log::error('Failed to sync petty cash approval to Firestore', [
                    'transaction_id' => $transaction->id,
                    'role' => $role,
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
     * Not gated on transaction status: the manager's approval alone flips status
     * to "approved" (it's what posts the journal entry — see isReadyToPost()), so
     * gating on status=pending would stop reconciling the auditor's approval (or
     * a receipt attached afterward) the moment it arrives after the manager's.
     */
    public function reconcileFromFirestore(PettyCashTransaction $transaction): PettyCashTransaction
    {
        if ($transaction->type !== 'expense') {
            return $transaction;
        }

        $doc = $this->firestore->fetch($transaction->id);
        if (! $doc) {
            return $transaction;
        }

        if (($doc['auditor_approved'] ?? false) && ! $transaction->auditor_approved_at) {
            $auditorId = (int) Setting::where('key', 'petty_cash_auditor_user_id')->value('value');
            if ($auditorId) {
                $transaction = $this->approve($transaction, 'auditor', $auditorId);
            }
        }

        if (($doc['manager_approved'] ?? false) && ! $transaction->manager_approved_at) {
            $managerId = (int) Setting::where('key', 'petty_cash_manager_user_id')->value('value');
            if ($managerId) {
                $transaction = $this->approve($transaction, 'manager', $managerId);
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

    private function postAndDeduct(PettyCashTransaction $transaction): void
    {
        $fund = $transaction->fund()->lockForUpdate()->first();

        if ((float) $transaction->amount > (float) $fund->current_balance) {
            abort(422, 'رصيد الصندوق غير كافٍ لاعتماد هذا المصروف.');
        }

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
                'account_id' => $fund->account_id,
                'debit' => 0,
                'credit' => $transaction->amount,
                'description' => $desc,
            ],
        ]);

        $transaction->update(['journal_entry_id' => $entry->id, 'status' => 'approved']);
        $fund->decrement('current_balance', $transaction->amount);
    }
}
