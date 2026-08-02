<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;

/**
 * Uploads petty cash receipts to Firebase Storage so the WhatsApp "عرض المستند"
 * quick-reply can be answered with a plain public link — Meta fetches media by
 * URL, so no Media-API upload/roundtrip through Laravel is needed at tap time.
 *
 * Talks to the Cloud Storage JSON API directly via the same pre-authenticated
 * Guzzle client used by FirestoreApprovalService (same reasoning: the official
 * SDK's gRPC path needs a PHP extension this environment doesn't have).
 */
class FirebaseStorageService
{
    private const BASE_URL = 'https://storage.googleapis.com';

    private const DOWNLOAD_URL = 'https://firebasestorage.googleapis.com/v0';

    private ?Client $apiClient = null;

    /**
     * Uploads $contents as $objectName and returns a public Firebase-style
     * download URL, or null when Firebase isn't configured (best-effort, same
     * no-op contract as FirestoreApprovalService).
     */
    public function upload(string $contents, string $objectName, string $mimeType): ?string
    {
        $client = $this->client();
        if (! $client) {
            return null;
        }

        $bucket = config('services.firebase.storage_bucket');
        $encodedName = rawurlencode($objectName);
        $token = (string) Str::uuid();

        $client->post(self::BASE_URL."/upload/storage/v1/b/{$bucket}/o", [
            'query' => ['uploadType' => 'media', 'name' => $objectName],
            'headers' => ['Content-Type' => $mimeType],
            'body' => $contents,
        ]);

        // Firebase Storage governs "public" download access via a per-object
        // token rather than making the object world-readable, so the bucket
        // itself can stay locked down while this one link still works for Meta.
        $client->patch(self::BASE_URL."/storage/v1/b/{$bucket}/o/{$encodedName}", [
            'json' => ['metadata' => ['firebaseStorageDownloadTokens' => $token]],
        ]);

        return self::DOWNLOAD_URL."/b/{$bucket}/o/{$encodedName}?alt=media&token={$token}";
    }

    private function client(): ?Client
    {
        if ($this->apiClient !== null) {
            return $this->apiClient;
        }

        $credentialsPath = config('services.firebase.credentials_path');
        if (! $credentialsPath || ! config('services.firebase.storage_bucket')) {
            return null;
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        if ($projectId = config('services.firebase.project_id')) {
            $factory = $factory->withProjectId($projectId);
        }

        return $this->apiClient = $factory->createApiClient();
    }
}
