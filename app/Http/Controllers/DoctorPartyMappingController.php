<?php

namespace App\Http\Controllers;

use App\Models\DoctorPartyMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorPartyMappingController extends Controller
{
    /** GET /api/doctor-party-mappings — returns { jawda_doctor_id: finance_party_id } */
    public function index(): JsonResponse
    {
        $map = DoctorPartyMapping::whereNotNull('finance_party_id')
            ->pluck('finance_party_id', 'jawda_doctor_id');

        return response()->json($map);
    }

    /** PUT /api/doctor-party-mappings/{jawda_doctor_id} */
    public function upsert(Request $request, int $jawdaDoctorId): JsonResponse
    {
        $data = $request->validate([
            'finance_party_id' => ['nullable', 'integer', 'exists:parties,id'],
        ]);

        $row = DoctorPartyMapping::updateOrCreate(
            ['jawda_doctor_id' => $jawdaDoctorId],
            ['finance_party_id' => $data['finance_party_id']]
        );

        return response()->json([
            'jawda_doctor_id'  => $row->jawda_doctor_id,
            'finance_party_id' => $row->finance_party_id,
        ]);
    }
}
