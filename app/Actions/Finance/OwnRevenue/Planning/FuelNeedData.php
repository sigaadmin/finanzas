<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

final readonly class FuelNeedData
{
    public function __construct(
        public int $activityId,
        public int $routeId,
        public ?string $commissionDateLabel,
        public int $operationalMonth,
        public string $reason,
        public string $vehicleModel,
        public string $kilometersPerLiter,
        public ?string $outboundOrigin,
        public ?string $outboundDestination,
        public ?string $outboundKilometers,
        public ?string $returnOrigin,
        public ?string $returnDestination,
        public ?string $returnKilometers,
        public ?string $additionalKilometers,
        public ?string $fuelPrice,
        public ?string $budgetAmountCents,
        public int $sortOrder,
        public ?string $overrideJustification,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            activityId: (int) $data['own_revenue_activity_id'],
            routeId: (int) $data['own_revenue_route_id'],
            commissionDateLabel: $data['commission_date_label'] ?? null,
            operationalMonth: (int) $data['operational_month'],
            reason: $data['reason'],
            vehicleModel: $data['vehicle_model'],
            kilometersPerLiter: $data['kilometers_per_liter'],
            outboundOrigin: $data['outbound_origin'] ?? null,
            outboundDestination: $data['outbound_destination'] ?? null,
            outboundKilometers: $data['outbound_kilometers'] ?? null,
            returnOrigin: $data['return_origin'] ?? null,
            returnDestination: $data['return_destination'] ?? null,
            returnKilometers: $data['return_kilometers'] ?? null,
            additionalKilometers: $data['additional_kilometers'] ?? null,
            fuelPrice: $data['fuel_price'] ?? null,
            budgetAmountCents: isset($data['budget_amount_cents']) ? (string) $data['budget_amount_cents'] : null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            overrideJustification: $data['override_justification'] ?? null,
        );
    }
}
