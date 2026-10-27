<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $validUid = '11111111-2222-3333-4444-555555555555';

    /** Invalid email is rejected */
    public function test_invalid_email_fails(): void
    {
        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address', 'not-an-email')
            ->expectsOutputToContain('Invalid email')
            ->assertFailed();
    }

    /** Duplicate admin email is rejected without modification */
    public function test_duplicate_admin_email_fails(): void
    {
        $existing = new User();
        $existing->name        = 'Existing Admin';
        $existing->email       = 'admin@example.com';
        $existing->supabase_id = $this->validUid;
        $existing->password    = bcrypt('password123456');
        $existing->role        = 'admin';
        $existing->save();

        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address', 'admin@example.com')
            ->expectsOutputToContain('already exists')
            ->assertFailed();

        // Role must NOT have changed
        $this->assertEquals('admin', $existing->fresh()->role);
    }

    /** Existing non-admin account is NOT silently promoted */
    public function test_existing_user_is_not_promoted(): void
    {
        $existing = new User();
        $existing->name        = 'Regular User';
        $existing->email       = 'user@example.com';
        $existing->supabase_id = $this->validUid;
        $existing->password    = bcrypt('password123456');
        $existing->role        = 'user';
        $existing->save();

        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address', 'user@example.com')
            ->expectsOutputToContain('not allowed')
            ->assertFailed();

        // Role must still be user, not promoted
        $this->assertEquals('user', $existing->fresh()->role);
    }

    /** Invalid Supabase UID format is rejected */
    public function test_invalid_supabase_uid_fails(): void
    {
        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address', 'admin@example.com')
            ->expectsQuestion('Supabase User UID (sub claim, UUID)', 'not-a-valid-uuid')
            ->expectsOutputToContain('Invalid Supabase UID')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }

    /** Duplicate Supabase UID is rejected */
    public function test_duplicate_supabase_uid_fails(): void
    {
        $existing = new User();
        $existing->name        = 'Other Admin';
        $existing->email       = 'other@example.com';
        $existing->supabase_id = $this->validUid;
        $existing->password    = bcrypt('password123456');
        $existing->role        = 'admin';
        $existing->save();

        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address', 'newadmin@example.com')
            ->expectsQuestion('Supabase User UID (sub claim, UUID)', $this->validUid)
            ->expectsOutputToContain('already bound to another local user')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'newadmin@example.com']);
    }

    /** Valid new admin is created with role=admin and supabase_id via explicit assignment */
    public function test_valid_new_admin_is_created(): void
    {
        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address',                 'newadmin@example.com')
            ->expectsQuestion('Supabase User UID (sub claim, UUID)', $this->validUid)
            ->expectsQuestion('Local password (min 12 chars)',       'SecurePass#9999')
            ->expectsQuestion('Confirm password',                    'SecurePass#9999')
            ->expectsQuestion('Full name (display purposes only)',   'New Admin')
            ->expectsOutputToContain('Admin account created successfully')
            ->assertSuccessful();

        $user = User::where('email', 'newadmin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('admin', $user->role);
        $this->assertEquals($this->validUid, $user->supabase_id);
        $this->assertEquals('New Admin', $user->name);

        // Neither role nor supabase_id may be in fillable
        $this->assertNotContains('role', (new User())->getFillable());
        $this->assertNotContains('supabase_id', (new User())->getFillable());
    }

    /** Short password is rejected */
    public function test_short_password_fails(): void
    {
        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address',                 'admin2@example.com')
            ->expectsQuestion('Supabase User UID (sub claim, UUID)', $this->validUid)
            ->expectsQuestion('Local password (min 12 chars)',       'short')
            ->expectsOutputToContain('at least 12')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin2@example.com']);
    }

    /** Mismatched passwords are rejected */
    public function test_mismatched_passwords_fail(): void
    {
        $this->artisan('aqua:create-admin')
            ->expectsQuestion('Admin email address',                 'admin3@example.com')
            ->expectsQuestion('Supabase User UID (sub claim, UUID)', $this->validUid)
            ->expectsQuestion('Local password (min 12 chars)',       'SecurePass#9999')
            ->expectsQuestion('Confirm password',                    'DifferentPass#000')
            ->expectsOutputToContain('do not match')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin3@example.com']);
    }
}
