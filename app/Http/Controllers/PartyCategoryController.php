<?php

namespace App\Http\Controllers;

use App\Models\PartyCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PartyCategory::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:party_categories,name'],
        ]);

        $category = PartyCategory::create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, PartyCategory $partyCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('party_categories', 'name')->ignore($partyCategory->id)],
        ]);

        $partyCategory->update($validated);

        return response()->json($partyCategory->fresh());
    }

    public function destroy(PartyCategory $partyCategory): JsonResponse
    {
        $partyCategory->delete();

        return response()->json(null, 204);
    }
}
