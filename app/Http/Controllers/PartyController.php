<?php

namespace App\Http\Controllers;

use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Party::with('account:id,code,name')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:customer,supplier,employee,other'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:255'],
            'address'    => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active'  => ['boolean'],
        ]);

        $party = Party::create($validated);

        return response()->json($party->load('account:id,code,name'), 201);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:customer,supplier,employee,other'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:255'],
            'address'    => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active'  => ['boolean'],
        ]);

        $party->update($validated);

        return response()->json($party->fresh()->load('account:id,code,name'));
    }

    public function destroy(Party $party): JsonResponse
    {
        $party->delete();

        return response()->json(null, 204);
    }
}
