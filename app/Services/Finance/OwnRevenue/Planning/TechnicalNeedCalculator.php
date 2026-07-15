<?php

namespace App\Services\Finance\OwnRevenue\Planning;

class TechnicalNeedCalculator
{
    public function __construct(private readonly FixedDecimal $decimal) {}

    public function calculate(string $quantity, string $unitPrice): TechnicalNeedCalculationResult
    {
        $quantity = $this->decimal->requireNonNegative($quantity);
        $unitPrice = $this->decimal->requireNonNegative($unitPrice);
        $referencePesos = $this->decimal->multiply($quantity, $unitPrice, 8);

        return new TechnicalNeedCalculationResult($this->decimal->centsHalfUp($referencePesos));
    }

    public function referenceCents(string $quantity, string $unitPrice): string
    {
        return $this->calculate($quantity, $unitPrice)->referenceCents;
    }
}
