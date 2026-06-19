<?php

namespace App\Services\Cloudinary;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Cloudinary upload service using the REST API directly.
 * No SDK required — uses Laravel's HTTP client (same pattern as ZernioClient).
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = (string) config('services.cloudinary.cloud_name', '');
        $this->apiKey    = (string) config('services.cloudinary.api_key', '');
        $this->apiSecret = (string) config('services.cloudinary.api_secret', '');
    }

    public function isConfigured(): bool
    {
        return $this->cloudName !== '' && $this->apiKey !== '' && $this->apiSecret !== '';
    }

    /**
     * Upload raw binary image data to Cloudinary.
     *
     * @param  string  $binaryData  Raw binary (e.g. decoded base64 PNG)
     * @param  string  $folder      Cloudinary folder path (e.g. "promotion-assets/2026/06")
     * @param  string  $publicId    Optional explicit public_id (UUID recommended)
     * @return string|null          Secure Cloudinary URL, or null on failure
     */
    public function uploadBinary(string $binaryData, string $folder = 'uploads', string $publicId = ''): ?string
    {
        if (! $this->isConfigured()) {
            Log::warning('[Cloudinary] Not configured — skipping upload.');
            return null;
        }

        // Encode as base64 data URI for the REST API
        $b64    = base64_encode($binaryData);
        $dataUri = "data:image/png;base64,{$b64}";

        return $this->uploadDataUri($dataUri, $folder, $publicId);
    }

    /**
     * Upload a base64 data URI string to Cloudinary.
     */
    public function uploadDataUri(string $dataUri, string $folder = 'uploads', string $publicId = ''): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $timestamp = time();
        $params    = array_filter([
            'folder'    => $folder,
            'public_id' => $publicId ?: null,
            'timestamp' => $timestamp,
        ]);

        $signature = $this->sign($params);

        try {
            $response = Http::timeout(60)
                ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", array_merge($params, [
                    'file'      => $dataUri,
                    'api_key'   => $this->apiKey,
                    'signature' => $signature,
                ]));

            if (! $response->successful()) {
                Log::warning('[Cloudinary] Upload failed', [
                    'status' => $response->status(),
                    'body'   => \Illuminate\Support\Str::limit($response->body(), 400),
                ]);
                return null;
            }

            return $response->json('secure_url');
        } catch (\Throwable $e) {
            Log::warning('[Cloudinary] Upload exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Upload from a remote URL (Cloudinary fetches it).
     */
    public function uploadFromUrl(string $remoteUrl, string $folder = 'uploads', string $publicId = ''): ?string
    {
        return $this->uploadDataUri($remoteUrl, $folder, $publicId);
    }

    /**
     * Build a Cloudinary signed request signature.
     * Only non-file, non-api_key params are included; sorted alphabetically.
     */
    private function sign(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = "{$key}={$value}";
            }
        }
        $paramString = implode('&', $parts);

        return hash('sha256', $paramString . $this->apiSecret);
    }
}
