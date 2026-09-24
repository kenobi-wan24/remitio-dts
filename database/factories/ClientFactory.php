<?php

namespace Database\Factories;

use App\Enums\ClientType;
use Database\Factories\Support\DemoData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_type' => ClientType::Individual,
            'first_name' => fake()->randomElement(DemoData::FIRST_NAMES),
            'last_name' => fake()->randomElement(DemoData::LAST_NAMES),
            'company_name' => null,
            'contact_number' => '09'.fake()->numerify('#########'),
            'email' => fake()->optional(0.6)->safeEmail(),
            'address' => fake()->randomElement(DemoData::BARANGAYS).', Davao City',
            'notes' => null,
        ];
    }

    public function company(): static
    {
        return $this->state(fn () => [
            'client_type' => ClientType::Company,
            'first_name' => null,
            'last_name' => null,
            'company_name' => DemoData::companyName(),
        ]);
    }
}
