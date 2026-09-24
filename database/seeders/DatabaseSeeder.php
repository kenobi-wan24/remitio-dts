<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // ⚠️ Do NOT add `use WithoutModelEvents;` here — the auto-generated
    // codes (CL-/RR-/DOC-) are created by model events.

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DocumentTypeSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
