<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Account::orderBy('code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(Account::create($validated), 201);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:20', 'unique:accounts,code,'.$account->id],
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ]);

        if (($validated['parent_id'] ?? null) === $account->id) {
            return response()->json(['message' => 'لا يمكن تعيين الحساب كأب لنفسه'], 422);
        }

        $account->update($validated);

        return response()->json($account->fresh());
    }

    public function destroy(Account $account): JsonResponse
    {
        if ($account->children()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف حساب يحتوي على حسابات فرعية'], 422);
        }

        $account->delete();

        return response()->json(null, 204);
    }
}
