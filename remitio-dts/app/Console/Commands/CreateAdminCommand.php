<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * php artisan dts:create-admin
 * Creates the first administrator on a fresh installation (no demo data).
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'dts:create-admin';

    protected $description = 'Create an administrator account (for first-time setup)';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Full name (e.g. Atty. Juan Remitio)'),
            'email' => $this->ask('Email (used to sign in)'),
            'password' => $this->secret('Password (8+ characters, letters and numbers)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([...$data, 'role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()]);

        $this->info("Administrator {$data['email']} created. Sign in and add the other accounts under Administration → Users.");

        return self::SUCCESS;
    }
}
