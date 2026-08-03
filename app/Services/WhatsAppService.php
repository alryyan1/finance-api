<?php

namespace App\Services;

use App\Models\PettyCashTransaction;
use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    /**
     * The dialable number and verified business name behind the configured
     * WHATSAPP_PHONE_NUMBER_ID, fetched from Meta's Graph API and cached for
     * an hour (it essentially never changes). Returns null if WhatsApp isn't
     * configured yet or the lookup fails — callers should treat that as "don't show it."
     *
     * @return array{display_phone_number: string, verified_name: string}|null
     */
    public function getBusinessPhoneNumber(): ?array
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.token');

        if (! $phoneNumberId || ! $token) {
            return null;
        }

        return Cache::remember("whatsapp.business_phone_number.{$phoneNumberId}", now()->addHour(), function () use ($phoneNumberId, $token) {
            try {
                $response = Http::withToken($token)->get(sprintf(
                    'https://graph.facebook.com/%s/%s',
                    config('services.whatsapp.api_version', 'v20.0'),
                    $phoneNumberId
                ), ['fields' => 'display_phone_number,verified_name']);

                if ($response->failed()) {
                    return null;
                }

                return [
                    'display_phone_number' => (string) $response->json('display_phone_number'),
                    'verified_name' => (string) $response->json('verified_name'),
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Send the approval-request template to the designated manager,
     * synchronously. Never throws — the outcome (sent, or skipped/failed with
     * the underlying reason) is reported back so the caller can show it to the user.
     *
     * @return array<int, array{role: string, phone: string, template: string, status: string, error: ?string}>
     */
    public function sendApprovalNotifications(PettyCashTransaction $transaction): array
    {
        $phone = (string) Setting::where('key', 'petty_cash_manager_whatsapp_phone')->value('value');

        return [
            $this->sendToRole($transaction, 'manager', $phone, (string) config('services.whatsapp.manager_template')),
        ];
    }

    /**
     * @return array{role: string, phone: string, template: string, status: string, error: ?string}
     */
    private function sendToRole(PettyCashTransaction $transaction, string $role, string $phone, string $template): array
    {
        if ($phone === '' || $template === '') {
            return ['role' => $role, 'phone' => $phone, 'template' => $template, 'status' => 'skipped', 'error' => 'رقم الهاتف أو اسم القالب غير مضبوط في الإعدادات.'];
        }

        try {
            $this->sendApprovalTemplate($phone, $template, $role, $transaction);

            return ['role' => $role, 'phone' => $phone, 'template' => $template, 'status' => 'sent', 'error' => null];
        } catch (RequestException $e) {
            $error = $e->response->json('error.message') ?? $e->getMessage();

            return ['role' => $role, 'phone' => $phone, 'template' => $template, 'status' => 'failed', 'error' => $error];
        } catch (\Throwable $e) {
            return ['role' => $role, 'phone' => $phone, 'template' => $template, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send the "petty cash expense needs approval" utility template to one approver.
     * Body has 5 variables (amount, description, user, date/time, beneficiary) and
     * two quick-reply buttons: approve, and view the attached receipt. Each button's
     * payload encodes the action, the Firestore collection name, the transaction id,
     * the approver role, and (for the document button) whether the receipt is an
     * image or a PDF — so the Cloud Function can act on a tap with zero lookups
     * beyond reading the mirror doc for the actual Storage link.
     */
    public function sendApprovalTemplate(string $toPhone, string $templateName, string $role, PettyCashTransaction $transaction): void
    {
        $collectionName = $this->collectionName();
        $documentType = $this->documentType($transaction);

        Http::withToken(config('services.whatsapp.token'))
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                config('services.whatsapp.api_version', 'v20.0'),
                config('services.whatsapp.phone_number_id')
            ), [
                'messaging_product' => 'whatsapp',
                'to' => $toPhone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => config('services.whatsapp.template_language', 'ar')],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => number_format((float) $transaction->amount, 2)],
                                ['type' => 'text', 'text' => $transaction->description ?? '—'],
                                ['type' => 'text', 'text' => $transaction->createdBy?->name ?? '—'],
                                ['type' => 'text', 'text' => $transaction->created_at->format('Y-m-d H:i')],
                                ['type' => 'text', 'text' => $transaction->beneficiary_name ?? '—'],
                            ],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'quick_reply',
                            'index' => '0',
                            'parameters' => [
                                ['type' => 'payload', 'payload' => "petty_cash_approvals:{$collectionName}:{$transaction->id}:{$role}"],
                            ],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'quick_reply',
                            'index' => '1',
                            'parameters' => [
                                ['type' => 'payload', 'payload' => "petty_cash_documents:{$collectionName}:{$transaction->id}:{$role}:{$documentType}"],
                            ],
                        ],
                    ],
                ],
            ])->throw();
    }

    /**
     * A plain free-form text message — only deliverable within the 24-hour
     * customer service window (i.e. in reply to something the recipient sent
     * recently), so this is for confirming/rejecting a WhatsApp-originated
     * action back to whoever just triggered it, not for cold outreach.
     */
    public function sendText(string $toPhone, string $body): void
    {
        Http::withToken(config('services.whatsapp.token'))
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                config('services.whatsapp.api_version', 'v20.0'),
                config('services.whatsapp.phone_number_id')
            ), [
                'messaging_product' => 'whatsapp',
                'to' => $toPhone,
                'type' => 'text',
                'text' => ['body' => $body],
            ])->throw();
    }

    private function collectionName(): string
    {
        return Setting::where('key', 'firebase_collection_name')->value('value') ?: 'jawda';
    }

    /**
     * 'image', 'document', or 'none' — mirrors the document_type field written
     * to the Firestore mirror doc, so the button payload and the mirror doc
     * agree on how the Cloud Function should send the file back on a tap.
     */
    private function documentType(PettyCashTransaction $transaction): string
    {
        if (! $transaction->document_path) {
            return 'none';
        }

        $mimeType = Storage::disk('local')->mimeType($transaction->document_path) ?: '';

        return str_starts_with($mimeType, 'image/') ? 'image' : 'document';
    }
}
