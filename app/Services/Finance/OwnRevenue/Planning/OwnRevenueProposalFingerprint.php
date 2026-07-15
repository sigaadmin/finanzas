<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use Illuminate\Database\Eloquent\Model;

class OwnRevenueProposalFingerprint
{
    public function __construct(
        private readonly CanonicalJson $canonicalJson,
        private readonly OwnRevenueProposalProjector $projector,
    ) {}

    public function forProposal(OwnRevenueProposal $proposal): string
    {
        $proposal->loadMissing(['budget', 'technicalNeeds', 'fuelNeeds', 'travelCommissions.participants']);

        return $this->canonicalJson->hash([
            'proposal' => [
                'id' => $proposal->id,
                'version_number' => $proposal->version_number,
                'status' => $proposal->status->value,
                'source_fingerprint' => $proposal->source_fingerprint,
                'source_file_ids' => [
                    $proposal->source_abpre_file_id,
                    $proposal->source_work_sheet_file_id,
                    $proposal->source_technical_sheet_file_id,
                    $proposal->source_fuel_file_id,
                    $proposal->source_travel_expenses_file_id,
                ],
            ],
            'parameters' => [
                'region_code' => $proposal->budget->region_code,
                'region_name' => $proposal->budget->region_name,
                'uma_value' => (string) $proposal->budget->uma_value,
                'fuel_price_per_liter' => (string) $proposal->budget->fuel_price_per_liter,
                'fuel_budget_month' => $proposal->budget->fuel_budget_month,
                'institution_name' => $proposal->budget->institution_name,
                'responsible_unit_code' => $proposal->budget->responsible_unit_code,
                'budget_program_code' => $proposal->budget->budget_program_code,
                'component_code' => $proposal->budget->component_code,
                'official_activity_code' => $proposal->budget->official_activity_code,
            ],
            'technical_needs' => $proposal->technicalNeeds->sortBy(fn ($need): array => [$need->sort_order, $need->id])->values()
                ->map(fn ($need): array => $this->attributes($need, [
                    'own_revenue_activity_id', 'expense_classification_id', 'stable_key',
                    'specific_item_code', 'specific_item_name', 'chapter_code', 'chapter_name',
                    'sequence', 'quantity', 'unit', 'description', 'unit_price_cents',
                    'reference_amount_cents', 'budget_amount_cents', 'budget_month',
                    'impact_on_goals', 'region_code', 'region_name', 'sort_order',
                ]))->all(),
            'fuel_needs' => $proposal->fuelNeeds->sortBy(fn ($need): array => [$need->sort_order, $need->id])->values()
                ->map(fn ($need): array => $this->attributes($need, [
                    'own_revenue_activity_id', 'own_revenue_route_id', 'stable_key', 'commission_date_label', 'operational_month',
                    'budget_month', 'reason', 'vehicle_model', 'kilometers_per_liter',
                    'outbound_origin', 'outbound_destination', 'outbound_kilometers',
                    'return_origin', 'return_destination', 'return_kilometers', 'additional_kilometers',
                    'total_kilometers', 'liters', 'fuel_price', 'mathematical_amount_cents',
                    'rounded_amount_cents', 'budget_amount_cents', 'rounding_difference_cents',
                    'override_justification', 'sort_order',
                ]))->all(),
            'travel_commissions' => $proposal->travelCommissions->sortBy(fn ($commission): array => [$commission->sort_order, $commission->id])->values()
                ->map(fn ($commission): array => [
                    ...$this->attributes($commission, [
                        'own_revenue_activity_id', 'own_revenue_travel_destination_id', 'stable_key',
                        'commission_date_label', 'operational_month', 'budget_month', 'reason', 'destination', 'food_zone',
                        'lodging_zone', 'uma_value', 'flight_amount_cents', 'participants_amount_cents',
                        'total_amount_cents', 'override_justification', 'sort_order',
                    ]),
                    'participants' => $commission->participants->sortBy(fn ($participant): array => [$participant->sort_order, $participant->id])->values()
                        ->map(fn ($participant): array => $this->attributes($participant, [
                            'own_revenue_activity_id', 'own_revenue_travel_rate_id', 'stable_key',
                            'person_name', 'position', 'commission_days', 'per_diem_uma', 'lodging_uma',
                            'per_diem_amount_cents', 'lodging_amount_cents', 'total_amount_cents', 'sort_order',
                        ]))->all(),
                ])->all(),
            'projection' => $this->projector->project($proposal),
        ]);
    }

    /** @param list<string> $keys @return array<string, mixed> */
    private function attributes(Model $model, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $model->getRawOriginal($key)])->all();
    }
}
