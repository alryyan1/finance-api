<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\PartyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PartyController extends Controller
{
    /**
     * POST /api/parties/resolve-external — used by external systems (sales-api, etc.)
     * that don't know finance-api's Party ids. Finds the Party already mapped to
     * this external record, or creates both the Party and the mapping on first contact.
     */
    public function resolveExternal(Request $request, PartyResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'source_system' => ['required', 'string', 'max:50'],
            'source_type' => ['required', 'string', 'max:50'],
            'source_id' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'type' => ['nullable', 'in:customer,supplier,employee,other,doctor'],
        ]);

        $party = $resolver->resolve(
            $data['source_system'],
            $data['source_type'],
            $data['source_id'],
            Arr::except($data, ['source_system', 'source_type', 'source_id']),
        );

        return response()->json($party);
    }

    public function index(): JsonResponse
    {
        return response()->json(
            Party::with('account:id,code,name')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:customer,supplier,employee,other,doctor'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ]);

        $party = Party::create($validated);

        return response()->json($party->load('account:id,code,name'), 201);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:customer,supplier,employee,other,doctor'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
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
