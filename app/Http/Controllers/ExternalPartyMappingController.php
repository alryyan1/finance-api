<?php

namespace App\Http\Controllers;

use App\Models\ExternalPartyMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalPartyMappingController extends Controller
{
    /** GET /api/party-mappings?source_system=sales-api&source_type=client — returns { source_id: party_id } */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_system' => ['required', 'string', 'max:50'],
            'source_type' => ['required', 'string', 'max:50'],
        ]);

        $map = ExternalPartyMapping::where('source_system', $data['source_system'])
            ->where('source_type', $data['source_type'])
            ->pluck('party_id', 'source_id');

        return response()->json($map);
    }

    /** PUT /api/party-mappings/{source_system}/{source_type}/{source_id} */
    public function upsert(Request $request, string $sourceSystem, string $sourceType, string $sourceId): JsonResponse
    {
        $data = $request->validate([
            'party_id' => ['required', 'integer', 'exists:parties,id'],
        ]);

        $mapping = ExternalPartyMapping::updateOrCreate(
            ['source_system' => $sourceSystem, 'source_type' => $sourceType, 'source_id' => $sourceId],
            ['party_id' => $data['party_id']]
        );

        return response()->json($mapping);
    }

    /** DELETE /api/party-mappings/{source_system}/{source_type}/{source_id} */
    public function destroy(string $sourceSystem, string $sourceType, string $sourceId): JsonResponse
    {
        ExternalPartyMapping::where('source_system', $sourceSystem)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();

        return response()->json(null, 204);
    }
}
