<?php

namespace Database\Factories;

use App\Enums\CivilStatus;
use App\Enums\ClientType;
use App\Enums\Gender;
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
            'is_retainer' => false,
            'notarization_only' => false,
            'first_name' => fake()->randomElement(DemoData::FIRST_NAMES),
            'middle_name' => fake()->optional(0.7)->randomElement(DemoData::LAST_NAMES),
            'last_name' => fake()->randomElement(DemoData::LAST_NAMES),
            'civil_status' => fake()->randomElement([CivilStatus::Single, CivilStatus::Single, CivilStatus::Married, CivilStatus::Widowed]),
            'gender' => fake()->randomElement(Gender::cases()),
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
            'is_retainer' => fake()->boolean(70), // most companies/cooperatives are retainer accounts
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'civil_status' => null,
            'gender' => null,
            'company_name' => DemoData::companyName(),
            'representative' => fake()->randomElement(DemoData::FIRST_NAMES).' '.fake()->randomElement(DemoData::LAST_NAMES).', General Manager',
            'business_permit_number' => 'BP-'.fake()->numerify('####-#####'),
            'tin' => fake()->numerify('###-###-###-000'),
        ]);
    }

    /** Spouse details for a married individual. */
    public function married(): static
    {
        return $this->state(fn () => [
            'civil_status' => CivilStatus::Married,
            'spouse_last_name' => fake()->randomElement(DemoData::LAST_NAMES),
            'spouse_first_name' => fake()->randomElement(DemoData::FIRST_NAMES),
        ]);
    }

    /** Walk-in client: recorded for notarization only. */
    public function walkIn(): static
    {
        return $this->state(fn () => [
            'notarization_only' => true,
            'is_retainer' => false,
        ]);
    }
}
