<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\OpeningBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpeningBalanceController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::orderBy('code')->get(['id', 'code', 'name', 'type']);
        $balances = OpeningBalance::all()->keyBy('account_id');

        $result = $accounts->map(fn ($a) => [
            'account_id' => $a->id,
            'code'       => $a->code,
            'name'       => $a->name,
            'type'       => $a->type,
            'debit'      => number_format((float) ($balances->get($a->id)?->debit  ?? 0), 2, '.', ''),
            'credit'     => number_format((float) ($balances->get($a->id)?->credit ?? 0), 2, '.', ''),
        ]);

        return response()->json($result);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            '*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            '*.debit'      => ['required', 'numeric', 'min:0'],
            '*.credit'     => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data as $row) {
            OpeningBalance::updateOrCreate(
                ['account_id' => $row['account_id']],
                ['debit' => $row['debit'], 'credit' => $row['credit']]
            );
        }

        return $this->index();
    }
}
