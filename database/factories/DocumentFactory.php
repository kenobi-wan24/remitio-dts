<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\LegalCase;
use Database\Factories\Support\DemoData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        [$title, $typeName] = fake()->randomElement(DemoData::DOCUMENTS);

        return [
            'title' => $title,
            'description' => null,
            'document_type_id' => fn () => DocumentType::firstOrCreate(['name' => $typeName])->id,
            'client_id' => Client::factory(),
            'legal_case_id' => null,
            'status' => DocumentStatus::Received,
            'current_holder_id' => null,
            'physical_location' => fake()->randomElement(DemoData::LOCATIONS),
            'date_received' => fake()->dateTimeBetween('-5 months', '-1 day'),
            'due_date' => fake()->optional(0.5)->dateTimeBetween('-1 week', '+1 month'),
        ];
    }

    /** Attach to a case (and that case's client). */
    public function forCase(LegalCase $case): static
    {
        return $this->state(fn () => [
            'client_id' => $case->client_id,
            'legal_case_id' => $case->id,
        ]);
    }
}
