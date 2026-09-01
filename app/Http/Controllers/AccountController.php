<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\FirestoreApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Account::orderBy('code')->get());
    }

    public function show(Account $account): JsonResponse
    {
        return response()->json($account);
    }

    public function store(Request $request, FirestoreApprovalService $firestore): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'sub_type' => ['nullable', 'in:main,sub,detail,current,non_current,long_term'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ]);

        $account = Account::create($validated);
        $this->syncExpenseAccounts($firestore);

        return response()->json($account, 201);
    }

    public function update(Request $request, Account $account, FirestoreApprovalService $firestore): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', "unique:accounts,code,{$account->id}"],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'sub_type' => ['nullable', 'in:main,sub,detail,current,non_current,long_term'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ]);

        if (($validated['parent_id'] ?? null) === $account->id) {
            return response()->json(['message' => 'لا يمكن تعيين الحساب كأب لنفسه'], 422);
        }

        $account->update($validated);
        $this->syncExpenseAccounts($firestore);

        return response()->json($account->fresh());
    }

    public function destroy(Account $account, FirestoreApprovalService $firestore): JsonResponse
    {
        if ($account->children()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف حساب يحتوي على حسابات فرعية'], 422);
        }

        if ($account->journalEntryLines()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف حساب مرتبط بقيود محاسبية'], 422);
        }

        $account->delete();
        $this->syncExpenseAccounts($firestore);

        return response()->json(null, 204);
    }

    /**
     * Best-effort: keeps the WhatsApp "new expense" Flow's account dropdown
     * in sync with the real chart of accounts. Never lets a Firestore hiccup
     * break account management itself.
     */
    private function syncExpenseAccounts(FirestoreApprovalService $firestore): void
    {
        try {
            $firestore->syncExpenseAccounts();
        } catch (\Throwable $e) {
            Log::error('Failed to sync expense accounts to Firestore', ['error' => $e->getMessage()]);
        }
    }
}
