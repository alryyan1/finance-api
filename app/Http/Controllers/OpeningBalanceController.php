<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\OpeningBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpeningBalanceController extends Controller
{
    /**
     * GET /api/opening-balances?fiscal_year_id=1
     * fiscal_year_id omitted → returns legacy global balances (null)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id']]);
        $fiscalYearId = $request->input('fiscal_year_id');

        $accounts = Account::orderBy('code')
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->get(['id', 'code', 'name', 'type']);

        $balances = OpeningBalance::when(
            $fiscalYearId,
            fn ($q) => $q->where('fiscal_year_id', $fiscalYearId),
            fn ($q) => $q->whereNull('fiscal_year_id')
        )->get()->keyBy('account_id');

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

    /**
     * PUT /api/opening-balances
     * Body: { fiscal_year_id?: number, rows: [{account_id, debit, credit}] }
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'fiscal_year_id'   => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'rows'             => ['required', 'array'],
            'rows.*.account_id'=> ['required', 'integer', 'exists:accounts,id'],
            'rows.*.debit'     => ['required', 'numeric', 'min:0'],
            'rows.*.credit'    => ['required', 'numeric', 'min:0'],
        ]);

        $fiscalYearId = $request->input('fiscal_year_id');
        $rows         = $request->input('rows');

        foreach ($rows as $row) {
            OpeningBalance::where('account_id', $row['account_id'])
                ->when(
                    $fiscalYearId,
                    fn ($q) => $q->where('fiscal_year_id', $fiscalYearId),
                    fn ($q) => $q->whereNull('fiscal_year_id')
                )
                ->delete();

            if ((float) $row['debit'] > 0 || (float) $row['credit'] > 0) {
                OpeningBalance::create([
                    'fiscal_year_id' => $fiscalYearId,
                    'account_id'     => $row['account_id'],
                    'debit'          => $row['debit'],
                    'credit'         => $row['credit'],
                ]);
            }
        }

        return $this->index($request);
    }
}
