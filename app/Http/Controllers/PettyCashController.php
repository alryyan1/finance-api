<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\PettyCashFund;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PettyCashController extends Controller
{
    // ─────────────────────────────────────────
    //  FUND
    // ─────────────────────────────────────────

    public function fund(): JsonResponse
    {
        $fund = PettyCashFund::with(['account:id,code,name', 'bankAccount:id,code,name'])
            ->first();

        return response()->json($fund);
    }

    public function setupFund(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:100'],
            'custodian_name'         => ['required', 'string', 'max:100'],
            'account_id'             => ['required', 'integer', 'exists:accounts,id'],
            'bank_account_id'        => ['required', 'integer', 'exists:accounts,id'],
            'max_amount'             => ['required', 'numeric', 'min:1'],
            'low_balance_threshold'  => ['required', 'numeric', 'min:0'],
        ]);

        $fund = PettyCashFund::first();

        if ($fund) {
            $fund->update($data);
        } else {
            $data['current_balance'] = 0;
            $fund = PettyCashFund::create($data);
        }

        return response()->json($fund->load(['account:id,code,name', 'bankAccount:id,code,name']));
    }

    // ─────────────────────────────────────────
    //  REQUESTS
    // ─────────────────────────────────────────

    public function requests(Request $request): JsonResponse
    {
        $fund = PettyCashFund::first();
        if (!$fund) return response()->json([]);

        $items = PettyCashRequest::where('fund_id', $fund->id)
            ->with('expenseAccount:id,code,name')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from,   fn ($q) => $q->where('date', '>=', $request->from))
            ->when($request->to,     fn ($q) => $q->where('date', '<=', $request->to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json($items);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $fund = PettyCashFund::firstOrFail();

        $data = $request->validate([
            'requester_name'    => ['required', 'string', 'max:100'],
            'date'              => ['required', 'date'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'category'          => ['required', 'in:' . implode(',', array_keys(PettyCashRequest::CATEGORIES))],
            'description'       => ['required', 'string', 'max:500'],
            'reference'         => ['nullable', 'string', 'max:50'],
            'expense_account_id'=> ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $data['fund_id'] = $fund->id;
        $data['status']  = 'pending';

        $item = PettyCashRequest::create($data);

        return response()->json($item->load('expenseAccount:id,code,name'), 201);
    }

    public function approveRequest(Request $request, PettyCashRequest $pettyCashRequest): JsonResponse
    {
        abort_if($pettyCashRequest->status !== 'pending', 422, 'الطلب ليس في حالة انتظار.');

        $data = $request->validate([
            'approved_by' => ['required', 'string', 'max:100'],
        ]);

        $pettyCashRequest->update([
            'status'      => 'approved',
            'approved_by' => $data['approved_by'],
            'approved_at' => now(),
        ]);

        return response()->json($pettyCashRequest);
    }

    public function rejectRequest(Request $request, PettyCashRequest $pettyCashRequest): JsonResponse
    {
        abort_if($pettyCashRequest->status !== 'pending', 422, 'الطلب ليس في حالة انتظار.');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:300'],
        ]);

        $pettyCashRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return response()->json($pettyCashRequest);
    }

    public function payRequest(Request $request, PettyCashRequest $pettyCashRequest): JsonResponse
    {
        abort_if($pettyCashRequest->status !== 'approved', 422, 'الطلب غير موافق عليه.');

        $data = $request->validate([
            'paid_by' => ['required', 'string', 'max:100'],
        ]);

        $fund = $pettyCashRequest->fund;

        abort_if((float) $fund->current_balance < (float) $pettyCashRequest->amount, 422,
            'رصيد الصندوق غير كافٍ لصرف هذا الطلب.');

        DB::transaction(function () use ($pettyCashRequest, $fund, $data) {
            $pettyCashRequest->update([
                'status'  => 'paid',
                'paid_by' => $data['paid_by'],
                'paid_at' => now(),
            ]);

            $fund->decrement('current_balance', $pettyCashRequest->amount);
        });

        return response()->json($pettyCashRequest->fresh()->load('fund'));
    }

    // ─────────────────────────────────────────
    //  REPLENISHMENTS
    // ─────────────────────────────────────────

    public function replenishments(): JsonResponse
    {
        $fund = PettyCashFund::first();
        if (!$fund) return response()->json([]);

        $items = PettyCashReplenishment::where('fund_id', $fund->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($items);
    }

    public function storeReplenishment(Request $request): JsonResponse
    {
        $fund = PettyCashFund::firstOrFail();

        $data = $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'description'  => ['nullable', 'string', 'max:300'],
            'requested_by' => ['required', 'string', 'max:100'],
        ]);

        $data['fund_id'] = $fund->id;
        $data['status']  = 'pending';

        $item = PettyCashReplenishment::create($data);

        return response()->json($item, 201);
    }

    public function approveReplenishment(Request $request, PettyCashReplenishment $replenishment): JsonResponse
    {
        abort_if($replenishment->status !== 'pending', 422, 'الطلب ليس في حالة انتظار.');

        $data = $request->validate([
            'approved_by' => ['required', 'string', 'max:100'],
        ]);

        $fund = $replenishment->fund;

        DB::transaction(function () use ($replenishment, $fund, $data) {
            // Create automatic journal entry: Dr. Petty Cash / Cr. Bank
            $entry = JournalEntry::create([
                'date'        => now()->toDateString(),
                'description' => 'تعبئة صندوق النثريات — ' . ($replenishment->description ?? ''),
                'reference'   => 'PC-' . $replenishment->id,
                'is_posted'   => true,
            ]);

            $entry->lines()->createMany([
                [
                    'account_id' => $fund->account_id,
                    'debit'      => $replenishment->amount,
                    'credit'     => 0,
                    'description'=> 'تعبئة صندوق النثريات',
                ],
                [
                    'account_id' => $fund->bank_account_id,
                    'debit'      => 0,
                    'credit'     => $replenishment->amount,
                    'description'=> 'تعبئة صندوق النثريات',
                ],
            ]);

            $replenishment->update([
                'status'          => 'approved',
                'approved_by'     => $data['approved_by'],
                'approved_at'     => now(),
                'journal_entry_id'=> $entry->id,
            ]);

            $fund->increment('current_balance', $replenishment->amount);
        });

        return response()->json($replenishment->fresh());
    }

    public function rejectReplenishment(Request $request, PettyCashReplenishment $replenishment): JsonResponse
    {
        abort_if($replenishment->status !== 'pending', 422, 'الطلب ليس في حالة انتظار.');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:300'],
        ]);

        $replenishment->update([
            'status'           => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return response()->json($replenishment);
    }

    public function categories(): JsonResponse
    {
        return response()->json(PettyCashRequest::CATEGORIES);
    }
}
