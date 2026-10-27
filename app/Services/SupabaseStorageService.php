<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SupabaseStorageService
{
    protected ?string $url;
    protected ?string $serviceRoleKey;
    protected string $bucket;

    public function __construct()
    {
        $this->url = rtrim(config('services.supabase.url') ?? '', '/');
        $this->serviceRoleKey = config('services.supabase.service_role_key');
        $this->bucket = config('services.supabase.storage_bucket', 'report-images');
    }

    /**
     * Check whether Supabase storage credentials are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->url) && !empty($this->serviceRoleKey);
    }

    /**
     * Upload an image file to Supabase private bucket.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string Object path within the bucket
     * @throws RuntimeException
     */
    public function upload(UploadedFile $file, string $folder = 'reports'): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Supabase Storage belum dikonfigurasi pada server.');
        }

        // Generate safe, collision-resistant object path using UUID
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $fileName = Str::uuid()->toString() . '.' . $extension;
        $objectPath = trim($folder, '/') . '/' . $fileName;

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Gagal membaca file foto untuk diunggah.');
        }

        $endpoint = "{$this->url}/storage/v1/object/{$this->bucket}/{$objectPath}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'apikey'        => $this->serviceRoleKey,
                'Content-Type'  => $mimeType,
            ])
            ->timeout(15)
            ->withBody($contents, $mimeType)
            ->post($endpoint);

            if (!$response->successful()) {
                Log::error('Supabase storage upload failed', [
                    'status' => $response->status(),
                    'error'  => $response->json(),
                ]);
                throw new RuntimeException('Gagal mengunggah foto ke penyimpanan cloud (status: ' . $response->status() . ').');
            }

            return $objectPath;
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            Log::error('Supabase storage upload exception', [
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Koneksi ke penyimpanan cloud gagal. Silakan coba beberapa saat lagi.');
        }
    }

    /**
     * Delete an object from the Supabase bucket (used for rollback/cleanup).
     *
     * @param string $objectPath
     * @return bool
     */
    public function delete(string $objectPath): bool
    {
        if (!$this->isConfigured() || empty($objectPath)) {
            return false;
        }

        $endpoint = "{$this->url}/storage/v1/object/{$this->bucket}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'apikey'        => $this->serviceRoleKey,
            ])
            ->timeout(10)
            ->delete($endpoint, [
                'prefixes' => [$objectPath],
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('Supabase storage object cleanup failed', [
                'path'    => $objectPath,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate a short-lived signed URL for a private bucket object.
     *
     * @param string|null $photoPath
     * @param int $expiresIn Expiration in seconds (default 3600 = 1 hour)
     * @return string|null
     */
    public function createSignedUrl(?string $photoPath, int $expiresIn = 3600): ?string
    {
        if (empty($photoPath)) {
            return null;
        }

        // Compatibility check: If photo_path is an existing local disk path or asset, keep it accessible
        if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
            return $photoPath;
        }

        // If local storage path (legacy report or fallback local test)
        if (!str_starts_with($photoPath, 'reports/') || !$this->isConfigured()) {
            if (file_exists(storage_path('app/public/' . $photoPath))) {
                return asset('storage/' . $photoPath);
            }
        }

        if (!$this->isConfigured()) {
            return null;
        }

        // Clean path to prevent traversal or malicious input
        $cleanPath = ltrim(str_replace(['../', '..\\'], '', $photoPath), '/');
        $endpoint = "{$this->url}/storage/v1/object/sign/{$this->bucket}/{$cleanPath}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'apikey'        => $this->serviceRoleKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->post($endpoint, [
                'expiresIn' => $expiresIn,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $signedUrl = $data['signedURL'] ?? null;
                if ($signedUrl) {
                    if (str_starts_with($signedUrl, 'http://') || str_starts_with($signedUrl, 'https://')) {
                        return $signedUrl;
                    }
                    return $this->url . '/storage/v1' . $signedUrl;
                }
            }

            Log::warning('Failed generating signed URL from Supabase', [
                'status' => $response->status(),
                'path'   => $cleanPath,
            ]);
        } catch (Throwable $e) {
            Log::warning('Exception generating signed URL', [
                'path'    => $cleanPath,
                'message' => $e->getMessage(),
            ]);
        }

        // Fallback to local storage if available
        if (file_exists(storage_path('app/public/' . $photoPath))) {
            return asset('storage/' . $photoPath);
        }

        return null;
    }
}
