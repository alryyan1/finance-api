<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_tax_number',
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::whereIn('key', self::ALLOWED_KEYS)->get()
            ->pluck('value', 'key');

        // Ensure all keys are present even if missing from DB
        $result = collect(self::ALLOWED_KEYS)
            ->mapWithKeys(fn ($k) => [$k => $settings->get($k, '')])
            ->all();

        return response()->json($result);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name'       => ['nullable', 'string', 'max:150'],
            'company_address'    => ['nullable', 'string', 'max:500'],
            'company_phone'      => ['nullable', 'string', 'max:50'],
            'company_email'      => ['nullable', 'email',  'max:150'],
            'company_tax_number' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key'   => $key],
                ['value' => $value ?? '']
            );
        }

        return $this->index();
    }
}
