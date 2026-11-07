<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostCenterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CostCenter::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cost_centers,name'],
        ]);

        $costCenter = CostCenter::create($validated);

        return response()->json($costCenter, 201);
    }

    public function update(Request $request, CostCenter $costCenter): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('cost_centers', 'name')->ignore($costCenter->id)],
        ]);

        $costCenter->update($validated);

        return response()->json($costCenter->fresh());
    }

    public function destroy(CostCenter $costCenter): JsonResponse
    {
        $costCenter->delete();

        return response()->json(null, 204);
    }
}
