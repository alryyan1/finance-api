<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\FirestoreApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        'journal_clinic_revenue_account_id',
        'journal_doctor_receivables_account_id',
        'journal_doctor_fees_expense_account_id',
        'petty_cash_manager_user_id',
        'petty_cash_manager_whatsapp_phone',
        'petty_cash_auditor_user_id',
        'petty_cash_notify_on_create',
        'petty_cash_cash_account_id',
        'petty_cash_bank_account_id',
        'firebase_collection_name',
        'sales_receivable_account_id',
        'sales_revenue_account_id',
        'sales_cogs_account_id',
        'sales_inventory_account_id',
        'sales_cash_account_id',
        'sales_bank_account_id',
    ];

    private const JOURNAL_INT_KEYS = [
        'journal_clinic_revenue_account_id',
        'journal_doctor_receivables_account_id',
        'journal_doctor_fees_expense_account_id',
    ];

    private const PETTY_CASH_INT_KEYS = [
        'petty_cash_manager_user_id',
        'petty_cash_auditor_user_id',
    ];

    private const PETTY_CASH_ACCOUNT_INT_KEYS = [
        'petty_cash_cash_account_id',
        'petty_cash_bank_account_id',
    ];

    /** Which chart-of-accounts account each role in an imported sales journal entry maps to. */
    private const SALES_BRIDGE_ACCOUNT_INT_KEYS = [
        'sales_receivable_account_id',
        'sales_revenue_account_id',
        'sales_cogs_account_id',
        'sales_inventory_account_id',
        'sales_cash_account_id',
        'sales_bank_account_id',
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

        // Cast journal account ID keys from string to int|null
        foreach (self::JOURNAL_INT_KEYS as $k) {
            $raw = $result[$k] ?? '';
            $result[$k] = ($raw !== '' && $raw !== null) ? (int) $raw : null;
        }

        // Cast petty cash manager user ID key from string to int|null
        foreach (self::PETTY_CASH_INT_KEYS as $k) {
            $raw = $result[$k] ?? '';
            $result[$k] = ($raw !== '' && $raw !== null) ? (int) $raw : null;
        }

        // Cast petty cash cash/bank account ID keys from string to int|null
        foreach (self::PETTY_CASH_ACCOUNT_INT_KEYS as $k) {
            $raw = $result[$k] ?? '';
            $result[$k] = ($raw !== '' && $raw !== null) ? (int) $raw : null;
        }

        // Cast sales bridge account ID keys from string to int|null
        foreach (self::SALES_BRIDGE_ACCOUNT_INT_KEYS as $k) {
            $raw = $result[$k] ?? '';
            $result[$k] = ($raw !== '' && $raw !== null) ? (int) $raw : null;
        }

        // Notify-on-create defaults to enabled, so installs that predate this
        // setting keep the original always-send behavior.
        $result['petty_cash_notify_on_create'] = $result['petty_cash_notify_on_create'] === ''
            ? true
            : filter_var($result['petty_cash_notify_on_create'], FILTER_VALIDATE_BOOLEAN);

        return response()->json($result);
    }

    public function update(Request $request, FirestoreApprovalService $firestore): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string',  'max:150'],
            'company_address' => ['nullable', 'string',  'max:500'],
            'company_phone' => ['nullable', 'string',  'max:50'],
            'company_email' => ['nullable', 'email',   'max:150'],
            'company_tax_number' => ['nullable', 'string',  'max:50'],
            'logo_position' => ['nullable', 'string',  'in:left,right,full'],
            'journal_clinic_revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'journal_doctor_receivables_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'journal_doctor_fees_expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'petty_cash_manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'petty_cash_manager_whatsapp_phone' => ['nullable', 'string', 'max:20'],
            'petty_cash_auditor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'petty_cash_notify_on_create' => ['nullable', 'boolean'],
            'petty_cash_cash_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'petty_cash_bank_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'firebase_collection_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'sales_receivable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sales_revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sales_cogs_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sales_inventory_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sales_cash_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sales_bank_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }

        $whatsappKeys = ['petty_cash_manager_whatsapp_phone', 'firebase_collection_name'];
        if (array_intersect($whatsappKeys, array_keys($data)) !== []) {
            try {
                $firestore->syncWhatsAppSenderConfig();
            } catch (\Throwable $e) {
                Log::error('Failed to sync WhatsApp sender config to Firestore', ['error' => $e->getMessage()]);
            }
        }

        $pettyCashAccountKeys = ['petty_cash_bank_account_id', 'petty_cash_cash_account_id'];
        if (array_intersect($pettyCashAccountKeys, array_keys($data)) !== []) {
            try {
                $firestore->syncPettyCashAccounts();
            } catch (\Throwable $e) {
                Log::error('Failed to sync petty cash credit accounts to Firestore', ['error' => $e->getMessage()]);
            }
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
        $rel = (string) ($setting?->value ?? '');
        if ($rel) {
            Storage::disk('public')->delete($rel);
            $setting?->update(['value' => '']);
        }

        return response()->json(['company_logo' => null]);
    }
}
