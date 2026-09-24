<?php

namespace Database\Factories;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Models\Client;
use Database\Factories\Support\DemoData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LegalCase>
 */
class LegalCaseFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(CaseType::cases());
        $status = fake()->randomElement([
            CaseStatus::Open, CaseStatus::Active, CaseStatus::Active,
            CaseStatus::Active, CaseStatus::OnHold, CaseStatus::Closed,
        ]);
        $opened = fake()->dateTimeBetween('-2 years', '-2 weeks');

        return [
            // client_id MUST stay above title (title closure reads it)
            'client_id' => Client::factory(),
            'case_type' => $type,
            'title' => fn (array $attributes) => $this->titleFor(
                $attributes['case_type'],
                Client::find($attributes['client_id'])?->display_name ?? 'Client',
            ),
            'docket_number' => $this->docketFor($type),
            'court_or_venue' => isset(DemoData::COURTS[$type->value])
                ? fake()->randomElement(DemoData::COURTS[$type->value])
                : null,
            'status' => $status,
            'date_opened' => $opened,
            'date_closed' => $status === CaseStatus::Closed
                ? fake()->dateTimeBetween($opened, 'now')
                : null,
            'description' => null,
        ];
    }

    private function titleFor(CaseType $type, string $clientName): string
    {
        $other = fake()->boolean(70) ? DemoData::personName() : DemoData::companyName();

        return match ($type) {
            CaseType::Criminal => "People of the Philippines vs. {$other}",
            CaseType::Civil, CaseType::Labor, CaseType::Administrative => "{$clientName} vs. {$other}",
            CaseType::SpecialProceeding => "In the Matter of the Petition of {$clientName}",
            CaseType::Notarial => "Notarial Services - {$clientName}",
            CaseType::Consultation => "Legal Consultation - {$clientName}",
            CaseType::Other => "Legal Matter - {$clientName}",
        };
    }

    private function docketFor(CaseType $type): ?string
    {
        $year = fake()->numberBetween(24, 26);
        $num = fake()->numerify('#####');

        return match ($type) {
            CaseType::Civil => "R-DVO-{$year}-{$num}-CV",
            CaseType::Criminal => "R-DVO-{$year}-{$num}-CR",
            CaseType::Labor => "RAB-XI-{$num}-{$year}",
            CaseType::Administrative => "ADM-{$year}-{$num}",
            CaseType::SpecialProceeding => "R-DVO-{$year}-{$num}-SP",
            default => null,
        };
    }
}
