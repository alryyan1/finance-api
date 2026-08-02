<?php

namespace App\Services;

use App\Models\PettyCashTransaction;
use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Kreait\Firebase\Factory;

/**
 * Mirrors petty cash approval state into Firestore, under finance/{company}/petty_cash_approvals/{id}.
 *
 * Talks to the Firestore REST API directly (rather than the google/cloud-firestore
 * SDK) because that SDK's gRPC transport requires the ext-grpc PHP extension, which
 * has no prebuilt Windows binary for this project's PHP build (8.2, thread-safe).
 * Kreait's Factory::createApiClient() already gives us a Guzzle client with the
 * service account's OAuth2 bearer token wired up, so REST is a light lift.
 */
class FirestoreApprovalService
{
    private const BASE_URL = 'https://firestore.googleapis.com/v1';

    private ?Client $apiClient = null;

    public function createMirror(PettyCashTransaction $transaction, ?string $documentUrl = null, ?string $documentType = null): void
    {
        $this->patch($this->documentPath((string) $transaction->id), [
            'transaction_id' => $transaction->id,
            'amount' => (float) $transaction->amount,
            'beneficiary_name' => $transaction->beneficiary_name,
            'description' => $transaction->description,
            'auditor_approved' => false,
            'manager_approved' => false,
            'document_url' => $documentUrl,
            'document_type' => $documentType,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Mark one role as approved. Safe to call from either the in-app approval
     * flow or the WhatsApp-tap Cloud Function — merge writes never clobber the
     * other role's field.
     */
    public function markApproved(int $transactionId, string $role): void
    {
        $this->patch($this->documentPath((string) $transactionId), [
            "{$role}_approved" => true,
            "{$role}_approved_at" => now()->toIso8601String(),
        ], merge: true);
    }

    /**
     * Attach (or replace) the document link on a mirror doc that already exists —
     * used when a receipt is uploaded after the expense was created.
     */
    public function updateDocument(int $transactionId, string $url, string $type): void
    {
        $this->patch($this->documentPath((string) $transactionId), [
            'document_url' => $url,
            'document_type' => $type,
        ], merge: true);
    }

    /**
     * Clears the document link off a mirror doc — used when a receipt is removed
     * without a replacement. The WhatsApp "عرض المستند" button reads document_url
     * live, so this takes effect immediately even on already-delivered messages.
     */
    public function clearDocument(int $transactionId): void
    {
        $this->patch($this->documentPath((string) $transactionId), [
            'document_url' => null,
            'document_type' => null,
        ], merge: true);
    }

    /**
     * Mirrors the configured manager/auditor WhatsApp numbers — and which
     * finance/{collectionName} each belongs to — into a top-level lookup the
     * pettyCashWebhook Cloud Function reads when it receives a bare image/PDF
     * (not a button tap): it authorizes who may attach a receipt via chat, and
     * tells the function which collection to list pending expenses from for
     * that phone. Called whenever petty cash approval settings are saved.
     */
    public function syncWhatsAppSenderConfig(): void
    {
        $collectionName = Setting::where('key', 'firebase_collection_name')->value('value') ?: 'jawda';
        $managerPhone = (string) Setting::where('key', 'petty_cash_manager_whatsapp_phone')->value('value');
        $auditorPhone = (string) Setting::where('key', 'petty_cash_auditor_whatsapp_phone')->value('value');

        foreach (['manager' => $managerPhone, 'auditor' => $auditorPhone] as $role => $phone) {
            $normalized = $this->normalizePhone($phone);
            if ($normalized === '') {
                continue;
            }

            $this->patch($this->senderConfigPath($normalized), [
                'collection_name' => $collectionName,
                'role' => $role,
            ]);
        }
    }

    public function delete(int $transactionId): void
    {
        $client = $this->client();
        if (! $client) {
            return;
        }

        $client->delete(self::BASE_URL.'/'.$this->documentPath((string) $transactionId));
    }

    /**
     * Read back the mirror doc's current fields — used to reconcile MySQL against
     * approvals that happened via a WhatsApp tap while nobody had the app open.
     * Returns null when Firebase isn't configured or the doc doesn't exist yet.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $transactionId): ?array
    {
        $client = $this->client();
        if (! $client) {
            return null;
        }

        try {
            $response = $client->get(self::BASE_URL.'/'.$this->documentPath((string) $transactionId));
        } catch (ClientException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }

        $body = json_decode((string) $response->getBody(), true);

        return $this->decodeFields($body['fields'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function patch(string $documentPath, array $fields, bool $merge = false): void
    {
        $client = $this->client();
        if (! $client) {
            return;
        }

        $url = self::BASE_URL.'/'.$documentPath;
        if ($merge) {
            $mask = collect(array_keys($fields))
                ->map(fn (string $field) => 'updateMask.fieldPaths='.urlencode($field))
                ->implode('&');
            $url .= '?'.$mask;
        }

        $client->patch($url, ['json' => ['fields' => $this->encodeFields($fields)]]);
    }

    /**
     * The petty_cash_approvals document path nested under finance/{collectionName},
     * where {collectionName} is the tenant identifier set in Settings > Petty Cash.
     * Falls back to "jawda" so an unconfigured value still lands somewhere sane.
     */
    private function documentPath(string $transactionId): string
    {
        $collectionName = Setting::where('key', 'firebase_collection_name')->value('value') ?: 'jawda';
        $projectId = config('services.firebase.project_id');

        return "projects/{$projectId}/databases/(default)/documents/finance/{$collectionName}/petty_cash_approvals/{$transactionId}";
    }

    private function senderConfigPath(string $normalizedPhone): string
    {
        $projectId = config('services.firebase.project_id');

        return "projects/{$projectId}/databases/(default)/documents/whatsapp_petty_cash_senders/{$normalizedPhone}";
    }

    /**
     * Matches the digits-only format WhatsApp uses for a message's "from" field
     * (e.g. "249991961111"), regardless of how the number was typed into Settings.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function encodeFields(array $fields): array
    {
        return array_map(fn (mixed $value) => match (true) {
            is_null($value) => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            default => ['stringValue' => (string) $value],
        }, $fields);
    }

    /**
     * Inverse of encodeFields() — unwraps Firestore's {type: value} field
     * envelopes back into plain scalars.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function decodeFields(array $fields): array
    {
        return array_map(fn (array $value) => match (true) {
            array_key_exists('nullValue', $value) => null,
            array_key_exists('booleanValue', $value) => $value['booleanValue'],
            array_key_exists('integerValue', $value) => (int) $value['integerValue'],
            array_key_exists('doubleValue', $value) => (float) $value['doubleValue'],
            array_key_exists('stringValue', $value) => $value['stringValue'],
            default => null,
        }, $fields);
    }

    /**
     * Lazily builds an authenticated Firestore REST client, and only when Firebase
     * credentials are actually configured — otherwise returns null so callers no-op.
     */
    private function client(): ?Client
    {
        if ($this->apiClient !== null) {
            return $this->apiClient;
        }

        $credentialsPath = config('services.firebase.credentials_path');
        if (! $credentialsPath) {
            return null;
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        if ($projectId = config('services.firebase.project_id')) {
            $factory = $factory->withProjectId($projectId);
        }

        return $this->apiClient = $factory->createApiClient();
    }
}
