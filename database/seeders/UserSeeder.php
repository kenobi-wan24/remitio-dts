<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional demo accounts. All passwords: "password"
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Atty. Carmela Reyes', 'email' => 'admin@remitio.test', 'role' => UserRole::Admin],
            ['name' => 'Atty. Daniel Uy', 'email' => 'attorney@remitio.test', 'role' => UserRole::Admin],
            ['name' => 'Jessa Mae Torres', 'email' => 'secretary@remitio.test', 'role' => UserRole::Staff],
            ['name' => 'Rodel Navarro', 'email' => 'clerk@remitio.test', 'role' => UserRole::Staff],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => 'password', // hashed by the model cast
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
