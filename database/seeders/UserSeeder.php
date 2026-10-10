<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional demo accounts (lawyers include fictional notarial commission details). All passwords: "password"
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Atty. Carmela Reyes', 'email' => 'admin@remitio.test', 'role' => UserRole::Admin,
                'notarial_commission_no' => '041-2026', 'commission_expires_on' => now()->endOfYear(), 'notary_office_address' => 'Bajada, Davao City'],
            ['name' => 'Atty. Daniel Uy', 'email' => 'attorney@remitio.test', 'role' => UserRole::Lawyer,
                'notarial_commission_no' => '052-2026', 'commission_expires_on' => now()->endOfYear(), 'notary_office_address' => 'Bajada, Davao City'],
            ['name' => 'Jessa Mae Torres', 'email' => 'secretary@remitio.test', 'role' => UserRole::Staff],
            ['name' => 'Rodel Navarro', 'email' => 'clerk@remitio.test', 'role' => UserRole::Staff],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'notarial_commission_no' => $user['notarial_commission_no'] ?? null,
                    'commission_expires_on' => $user['commission_expires_on'] ?? null,
                    'notary_office_address' => $user['notary_office_address'] ?? null,
                    'password' => 'password', // hashed by the model cast
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
