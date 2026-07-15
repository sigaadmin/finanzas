<?php

namespace App\Services\Finance\OwnRevenue\Planning;

final readonly class FuelNeedCalculationResult
{
    public function __construct(
        public string $liters,
        public string $mathematicalCents,
        public string $roundedCents,
        public string $budgetedCents,
        public string $roundingDifferenceCents,
    ) {}
}
