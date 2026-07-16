<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use DivisionByZeroError;

class FuelNeedCalculator
{
    public function __construct(private readonly FixedDecimal $decimal) {}

    public function calculate(
        string $kilometers,
        string $kilometersPerLiter,
        string $fuelPrice,
    ): FuelNeedCalculationResult {
        $kilometers = $this->decimal->requireNonNegative($kilometers);
        $kilometersPerLiter = $this->decimal->requireNonNegative($kilometersPerLiter);
        $fuelPrice = $this->decimal->requireNonNegative($fuelPrice);
        if ($this->decimal->compare($kilometersPerLiter, '0') === 0) {
            throw new DivisionByZeroError('El rendimiento debe ser mayor que cero.');
        }

        $liters = $this->decimal->divideCeiling($kilometers, $kilometersPerLiter, 4);
        $mathematicalPesos = $this->decimal->multiply($liters, $fuelPrice, 8);
        $mathematicalCents = $this->decimal->centsHalfUp($mathematicalPesos);
        $roundedCents = $this->decimal->roundCentsUpToPeso($mathematicalCents);
        $budgetedCents = $this->decimal->roundCentsUpToMultiple($roundedCents, 5000);
        $roundingDifferenceCents = $this->decimal->centsInteger(
            $this->decimal->subtract($budgetedCents, $mathematicalCents, 0),
        );

        return new FuelNeedCalculationResult(
            liters: $liters,
            mathematicalCents: $mathematicalCents,
            roundedCents: $roundedCents,
            budgetedCents: $budgetedCents,
            roundingDifferenceCents: $roundingDifferenceCents,
        );
    }
}
