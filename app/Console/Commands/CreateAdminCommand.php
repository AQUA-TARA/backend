<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class CreateAdminCommand extends Command
{
    protected $signature = 'aqua:create-admin';

    protected $description =
        'Provision a new admin user in the local Laravel users table bound to a Supabase User UID. '
        . 'This does NOT create or modify accounts in Supabase Auth. '
        . 'Admin access requires both this local record (role=admin, matching supabase_id) '
        . 'and app_metadata.role=admin set in the Supabase project.';

    public function handle(): int
    {
        $this->info('=== AQUA-TARA Admin Provisioning ===');
        $this->newLine();
        $this->warn('IMPORTANT: This command provisions a record in the local users table only.');
        $this->warn('It does NOT communicate with, verify, or create users in Supabase Auth.');
        $this->warn(
            'The operator MUST ensure the Supabase UID is obtained from a valid Supabase Auth user '
            . 'and that app_metadata.role = admin has been set through a trusted channel.'
        );
        $this->newLine();

        // Check schema prerequisite
        if (!Schema::hasColumn('users', 'supabase_id') || !Schema::hasColumn('users', 'role')) {
            $this->error('Database schema is pending required migrations (supabase_id or role column missing).');
            return self::FAILURE;
        }

        // -- 1. Email
        $email = trim((string) $this->ask('Admin email address'));
        $vEmail = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'string', 'email']]
        );
        if ($vEmail->fails()) {
            $this->error('Invalid email: ' . implode(' ', $vEmail->errors()->all()));
            return self::FAILURE;
        }

        // Existing email check - prevent duplicate or silent promotion
        $existing = User::where('email', $email)->first();
        if ($existing !== null) {
            if ($existing->role === 'admin') {
                $this->warn('Admin account for [' . $email . '] already exists. No changes made.');
            } else {
                $this->error(
                    'Non-admin account for [' . $email . '] already exists. '
                    . 'Automatic promotion is not allowed. '
                    . 'Update the role column via a trusted migration or seed.'
                );
            }
            return self::FAILURE;
        }

        // -- 2. Supabase User UID (sub claim)
        $supabaseId = trim((string) $this->ask('Supabase User UID (sub claim, UUID)'));
        $vUid = Validator::make(
            ['supabase_id' => $supabaseId],
            [
                'supabase_id' => [
                    'required',
                    'string',
                    'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
                ],
            ],
            [
                'supabase_id.regex' => 'The Supabase UID must be a valid UUID string (e.g. 12345678-1234-1234-1234-123456789abc).',
            ]
        );
        if ($vUid->fails()) {
            $this->error('Invalid Supabase UID: ' . implode(' ', $vUid->errors()->all()));
            return self::FAILURE;
        }

        // Check if supabase_id is already bound to another local account
        $existingUid = User::where('supabase_id', $supabaseId)->first();
        if ($existingUid !== null) {
            $this->error('Supabase UID [' . $supabaseId . '] is already bound to another local user (' . $existingUid->email . ').');
            return self::FAILURE;
        }

        // -- 3. Password
        $password = $this->secret('Local password (min 12 chars)');
        if (strlen((string) $password) < 12) {
            $this->error('Password must be at least 12 characters.');
            return self::FAILURE;
        }
        $confirm = $this->secret('Confirm password');
        if ($password !== $confirm) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        // -- 4. Name
        $name = trim((string) $this->ask('Full name (display purposes only)'));
        if (empty($name)) {
            $this->error('Name cannot be empty.');
            return self::FAILURE;
        }

        // -- 5. Persist -- role and supabase_id via explicit assignment, NOT mass assignment
        $user              = new User();
        $user->name        = $name;
        $user->email       = $email;
        $user->supabase_id = $supabaseId;
        $user->password    = Hash::make($password);
        $user->role        = 'admin';
        $user->save();
        unset($password, $confirm);

        $this->newLine();
        $this->info('Admin account created successfully.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Email',       $user->email],
                ['Supabase UID',$user->supabase_id],
                ['Name',        $user->name],
                ['Role',        $user->role],
                ['ID',          $user->id],
            ]
        );
        $this->newLine();
        $this->warn(
            'Next step: Ensure app_metadata.role = admin is set for this user in Supabase Auth '
            . 'so the Dual-Authorization middleware grants admin access.'
        );

        return self::SUCCESS;
    }
}
