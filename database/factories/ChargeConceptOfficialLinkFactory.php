<?php

namespace Database\Factories;

use App\Enums\Finance\OfficialFeeLinkStatus;
use App\Models\ChargeConcept;
use App\Models\ChargeConceptOfficialLink;
use App\Models\OfficialFeeConcept;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeConceptOfficialLink>
 */
class ChargeConceptOfficialLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'charge_concept_id' => ChargeConcept::factory(),
            'official_fee_concept_id' => OfficialFeeConcept::factory(),
            'fiscal_year' => now()->year,
            'status' => OfficialFeeLinkStatus::Linked,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
