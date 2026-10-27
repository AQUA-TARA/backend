<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SetSupabaseAdminRoleCommand extends Command
{
    protected $signature = 'aqua:set-supabase-admin-role
                            {uid : The Supabase Auth User UID (UUID)}
                            {--email= : The expected email address of the target account for verification}';

    protected $description =
        'Temporary administrative command to set app_metadata.role = "admin" on an existing Supabase Auth user. '
        . 'Performs read-before-write validation using the Supabase Auth Admin API and server-side service role key.';

    public function handle(): int
    {
        $this->info('=== AQUA-TARA Supabase Admin Role Assignment ===');
        $this->newLine();

        // 1. Validate environment configuration
        $supabaseUrl = config('services.supabase.url') ?: env('SUPABASE_URL');
        $serviceRoleKey = config('services.supabase.service_role_key') ?: env('SUPABASE_SERVICE_ROLE_KEY');

        if (empty($supabaseUrl) || empty($serviceRoleKey)) {
            $this->error('Supabase server-side configuration is missing. Ensure SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are set.');
            return self::FAILURE;
        }

        $baseUrl = rtrim((string) $supabaseUrl, '/');

        // Ensure SUPABASE_URL is an HTTP/HTTPS API endpoint, not a database host or connection pooler
        if (!str_starts_with($baseUrl, 'http://') && !str_starts_with($baseUrl, 'https://')) {
            $this->error('SUPABASE_URL must be a valid HTTP or HTTPS API endpoint URL (e.g. https://<project>.supabase.co), not a database host.');
            return self::FAILURE;
        }

        if (str_contains($baseUrl, 'pooler.supabase.com') || str_contains($baseUrl, ':5432') || str_contains($baseUrl, ':6543')) {
            $this->error('SUPABASE_URL points to a PostgreSQL connection pooler or database port. It must be the Supabase REST/Auth API URL.');
            return self::FAILURE;
        }

        // 2. Validate UID argument format
        $uid = trim((string) $this->argument('uid'));
        $vUid = Validator::make(
            ['uid' => $uid],
            [
                'uid' => [
                    'required',
                    'string',
                    'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
                ],
            ],
            [
                'uid.regex' => 'The provided UID must be a valid UUID string (e.g. 12345678-1234-1234-1234-123456789abc).',
            ]
        );

        if ($vUid->fails()) {
            $this->error('Invalid Supabase UID format: ' . implode(' ', $vUid->errors()->all()));
            return self::FAILURE;
        }

        // 3. Obtain and validate target email
        $expectedEmail = $this->option('email');
        if (empty($expectedEmail)) {
            $expectedEmail = $this->ask('Target email for verification');
        }
        $expectedEmail = trim(strtolower((string) $expectedEmail));

        $vEmail = Validator::make(
            ['email' => $expectedEmail],
            ['email' => ['required', 'string', 'email']]
        );

        if ($vEmail->fails()) {
            $this->error('Invalid target email format: ' . implode(' ', $vEmail->errors()->all()));
            return self::FAILURE;
        }

        $this->line("Querying Supabase Auth Admin API for user [{$uid}]...");

        // 4. Fetch user via Admin API (GET /auth/v1/admin/users/{uid})
        try {
            $getResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $serviceRoleKey,
                'apikey'        => $serviceRoleKey,
            ])
            ->timeout(15)
            ->get("{$baseUrl}/auth/v1/admin/users/{$uid}");
        } catch (Throwable $e) {
            $this->error('Network connection to Supabase Admin API failed.');
            return self::FAILURE;
        }

        if ($getResponse->status() === 404) {
            $this->error("Supabase user not found for UID [{$uid}].");
            return self::FAILURE;
        }

        if (!$getResponse->successful()) {
            $this->error("Failed to fetch user from Supabase Admin API (HTTP status: {$getResponse->status()}).");
            return self::FAILURE;
        }

        $userData = $getResponse->json();
        $remoteEmail = trim(strtolower((string) ($userData['email'] ?? '')));

        if ($remoteEmail !== $expectedEmail) {
            $this->error("Target email mismatch! The account with UID [{$uid}] belongs to a different email address.");
            return self::FAILURE;
        }

        // 5. Preserve existing app_metadata, only set role = admin
        $existingAppMeta = is_array($userData['app_metadata'] ?? null) ? $userData['app_metadata'] : [];
        $updatedAppMeta = array_merge($existingAppMeta, ['role' => 'admin']);

        // Check if role is already admin
        if (($existingAppMeta['role'] ?? null) === 'admin') {
            $this->warn("User [{$uid}] already has app_metadata.role = 'admin'. Re-applying role to confirm.");
        }

        // 6. Update user via Admin API (PUT /auth/v1/admin/users/{uid})
        try {
            $updateResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $serviceRoleKey,
                'apikey'        => $serviceRoleKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(15)
            ->put("{$baseUrl}/auth/v1/admin/users/{$uid}", [
                'app_metadata' => $updatedAppMeta,
            ]);
        } catch (Throwable $e) {
            $this->error('Network connection to Supabase Admin API failed during update.');
            return self::FAILURE;
        }

        if (!$updateResponse->successful()) {
            $this->error("Failed to update user role in Supabase Admin API (HTTP status: {$updateResponse->status()}).");
            return self::FAILURE;
        }

        // 7. Read-back verification: re-fetch user and verify app_metadata.role === 'admin'
        $this->line("Verifying updated state via read-back...");
        try {
            $verifyResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $serviceRoleKey,
                'apikey'        => $serviceRoleKey,
            ])
            ->timeout(15)
            ->get("{$baseUrl}/auth/v1/admin/users/{$uid}");
        } catch (Throwable $e) {
            $this->error('Update was submitted, but read-back verification failed due to network error.');
            return self::FAILURE;
        }

        if (!$verifyResponse->successful()) {
            $this->error("Update was submitted, but read-back verification returned HTTP status {$verifyResponse->status()}.");
            return self::FAILURE;
        }

        $verifiedData = $verifyResponse->json();
        $verifiedRole = $verifiedData['app_metadata']['role'] ?? null;

        if ($verifiedRole !== 'admin') {
            $this->error("Verification failed: app_metadata.role was not set to 'admin' on Supabase Auth.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully assigned and verified app_metadata.role = 'admin' for user UID [{$uid}].");
        $this->line("Verified Email : {$expectedEmail}");
        $this->line("Assigned Role  : admin");
        $this->newLine();

        return self::SUCCESS;
    }
}
