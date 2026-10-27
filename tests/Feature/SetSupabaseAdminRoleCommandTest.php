<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetSupabaseAdminRoleCommandTest extends TestCase
{
    private string $validUid = '12345678-1234-1234-1234-123456789abc';
    private string $testEmail = 'admin@aquatara.test';
    private string $supabaseUrl = 'https://test-project.supabase.co';
    private string $testKey = 'dummy-service-role-key-for-testing';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.supabase.url', $this->supabaseUrl);
        Config::set('services.supabase.service_role_key', $this->testKey);
    }

    /** 1. Command fails if configuration is missing */
    public function test_missing_configuration_fails(): void
    {
        Config::set('services.supabase.url', null);
        Config::set('services.supabase.service_role_key', null);
        putenv('SUPABASE_URL=');
        putenv('SUPABASE_SERVICE_ROLE_KEY=');

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain('Supabase server-side configuration is missing')
        ->assertFailed();
    }

    /** 2. Command fails if SUPABASE_URL is not an HTTP/HTTPS URL or points to DB host/pooler */
    public function test_invalid_supabase_url_scheme_or_pooler_fails(): void
    {
        // Case A: Missing http/https scheme
        Config::set('services.supabase.url', 'db.hnsudflvtresqeqfccxk.supabase.co');
        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain('SUPABASE_URL must be a valid HTTP or HTTPS API endpoint URL')
        ->assertFailed();

        // Case B: Points to connection pooler
        Config::set('services.supabase.url', 'https://aws-0-ap-southeast-2.pooler.supabase.com:6543');
        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain('SUPABASE_URL points to a PostgreSQL connection pooler')
        ->assertFailed();
    }

    /** 3. Command fails if UID format is not a valid UUID */
    public function test_invalid_uid_format_fails(): void
    {
        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => 'invalid-uuid-string',
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain('Invalid Supabase UID format')
        ->assertFailed();
    }

    /** 4. Command fails if user is not found in Supabase Auth (404) and PUT is never sent */
    public function test_user_not_found_fails_and_does_not_call_put(): void
    {
        Http::fake([
            "{$this->supabaseUrl}/auth/v1/admin/users/{$this->validUid}" => Http::response([
                'message' => 'User not found',
            ], 404),
        ]);

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain("Supabase user not found for UID [{$this->validUid}]")
        ->assertFailed();

        Http::assertNotSent(function ($request) {
            return $request->method() === 'PUT';
        });
    }

    /** 5. Command fails if target email does not match remote Supabase user email and PUT is never sent */
    public function test_email_mismatch_fails_and_does_not_call_put(): void
    {
        Http::fake([
            "{$this->supabaseUrl}/auth/v1/admin/users/{$this->validUid}" => Http::response([
                'id' => $this->validUid,
                'email' => 'different@aquatara.test',
                'app_metadata' => ['provider' => 'email'],
            ], 200),
        ]);

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain('Target email mismatch!')
        ->assertFailed();

        // Verify PUT update was NEVER called
        Http::assertNotSent(function ($request) {
            return $request->method() === 'PUT';
        });
    }

    /** 6. Command fails if Supabase Admin API returns an error on update (e.g. 500) */
    public function test_api_failure_on_update_fails(): void
    {
        Http::fake([
            "{$this->supabaseUrl}/auth/v1/admin/users/{$this->validUid}" => Http::sequence()
                ->push([
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email', 'custom_key' => 'existing_val'],
                ], 200)
                ->push(['message' => 'Internal server error'], 500),
        ]);

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain('Failed to update user role in Supabase Admin API')
        ->assertFailed();
    }

    /** 7. Command fails if read-back verification reveals role is not admin */
    public function test_read_back_verification_failure(): void
    {
        Http::fake([
            "{$this->supabaseUrl}/auth/v1/admin/users/{$this->validUid}" => Http::sequence()
                ->push([ // Initial GET
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email'],
                ], 200)
                ->push([ // PUT response
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email', 'role' => 'admin'],
                ], 200)
                ->push([ // Read-back GET returning unexpected non-admin role
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email', 'role' => 'user'],
                ], 200),
        ]);

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain("Verification failed: app_metadata.role was not set to 'admin'")
        ->assertFailed();
    }

    /** 8. Command successfully preserves existing app_metadata, sets role = admin, and verifies via read-back */
    public function test_successful_role_assignment_with_read_back_verification(): void
    {
        Http::fake([
            "{$this->supabaseUrl}/auth/v1/admin/users/{$this->validUid}" => Http::sequence()
                ->push([ // 1. Initial GET
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => [
                        'provider' => 'email',
                        'subscription' => 'enterprise',
                    ],
                ], 200)
                ->push([ // 2. PUT update
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => [
                        'provider' => 'email',
                        'subscription' => 'enterprise',
                        'role' => 'admin',
                    ],
                ], 200)
                ->push([ // 3. Read-back GET
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => [
                        'provider' => 'email',
                        'subscription' => 'enterprise',
                        'role' => 'admin',
                    ],
                ], 200),
        ]);

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
            '--email' => $this->testEmail,
        ])
        ->expectsOutputToContain("Successfully assigned and verified app_metadata.role = 'admin'")
        ->expectsOutputToContain("Verified Email : {$this->testEmail}")
        ->assertSuccessful();

        // Verify the exact payload sent in the PUT request: only role added, others preserved
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') {
                return false;
            }
            $body = $request->data();
            return isset($body['app_metadata']['role'])
                && $body['app_metadata']['role'] === 'admin'
                && ($body['app_metadata']['provider'] ?? null) === 'email'
                && ($body['app_metadata']['subscription'] ?? null) === 'enterprise';
        });
    }

    /** 9. Command interactive email prompt works when option is omitted */
    public function test_interactive_email_prompt_success(): void
    {
        Http::fake([
            "{$this->supabaseUrl}/auth/v1/admin/users/{$this->validUid}" => Http::sequence()
                ->push([
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email'],
                ], 200)
                ->push([
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email', 'role' => 'admin'],
                ], 200)
                ->push([
                    'id' => $this->validUid,
                    'email' => $this->testEmail,
                    'app_metadata' => ['provider' => 'email', 'role' => 'admin'],
                ], 200),
        ]);

        $this->artisan('aqua:set-supabase-admin-role', [
            'uid' => $this->validUid,
        ])
        ->expectsQuestion('Target email for verification', $this->testEmail)
        ->expectsOutputToContain("Successfully assigned and verified app_metadata.role = 'admin'")
        ->assertSuccessful();
    }
}
