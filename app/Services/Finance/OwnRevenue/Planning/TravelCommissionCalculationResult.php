<?php

namespace App\Services\Finance\OwnRevenue\Planning;

final readonly class TravelCommissionCalculationResult
{
    public function __construct(
        public string $perDiemCents,
        public string $lodgingCents,
        public string $flightCents,
        public string $totalCents,
    ) {}
}
