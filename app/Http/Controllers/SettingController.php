<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_tax_number',
        'logo_position',
    ];

    public function index(): JsonResponse
    {
        $all = Setting::whereIn('key', [...self::ALLOWED_KEYS, 'company_logo'])
            ->get()->pluck('value', 'key');

        $result = collect(self::ALLOWED_KEYS)
            ->mapWithKeys(fn ($k) => [$k => $all->get($k, '')])
            ->all();

        // Return the raw storage-relative path (e.g. "logos/file.jpg").
        // The frontend constructs the full URL using its own BACKEND_URL constant.
        $logoRel = $all->get('company_logo', '');
        $result['company_logo'] = $logoRel ?: null;

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
            'logo_position'      => ['nullable', 'string', 'in:left,right,full'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key'   => $key],
                ['value' => $value ?? '']
            );
        }

        return $this->index();
    }

    /** POST /api/settings/logo — upload or replace company logo */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:3072'],
        ]);

        // Delete previous logo file
        $old = Setting::where('key', 'company_logo')->value('value');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('logo')->store('logos', 'public');

        Setting::updateOrCreate(
            ['key' => 'company_logo'],
            ['value' => $path]
        );

        // Return the raw path — frontend constructs the full URL via BACKEND_URL.
        return response()->json(['company_logo' => $path]);
    }

    /** DELETE /api/settings/logo — remove company logo */
    public function deleteLogo(): JsonResponse
    {
        $setting = Setting::where('key', 'company_logo')->first();
        $rel     = (string) ($setting?->value ?? '');
        if ($rel) {
            Storage::disk('public')->delete($rel);
            $setting?->update(['value' => '']);
        }

        return response()->json(['company_logo' => null]);
    }
}
