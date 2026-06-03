<?php

namespace App\Http\Controllers;

use App\Models\UserJournalAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserJournalAccountController extends Controller
{
    /** GET /api/user/journal-accounts */
    public function show(Request $request): JsonResponse
    {
        $row = UserJournalAccount::firstOrNew(
            ['user_id' => $request->user()->id]
        );

        return response()->json([
            'cash_box_account_id' => $row->cash_box_account_id,
            'bank_account_id'     => $row->bank_account_id,
        ]);
    }

    /** PUT /api/user/journal-accounts */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cash_box_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'bank_account_id'     => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $row = UserJournalAccount::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return response()->json([
            'cash_box_account_id' => $row->cash_box_account_id,
            'bank_account_id'     => $row->bank_account_id,
        ]);
    }
}
