<?php

namespace App\Services\Finance\OwnRevenue\Planning;

class TravelCommissionCalculator
{
    public function __construct(private readonly FixedDecimal $decimal) {}

    public function calculate(
        string $commissionDays,
        string $perDiemUma,
        string $lodgingUma,
        string $umaValue,
        string $flightCents,
    ): TravelCommissionCalculationResult {
        $commissionDays = $this->decimal->requireNonNegative($commissionDays);
        $perDiemUma = $this->decimal->requireNonNegative($perDiemUma);
        $lodgingUma = $this->decimal->requireNonNegative($lodgingUma);
        $umaValue = $this->decimal->requireNonNegative($umaValue);
        $flightCents = $this->decimal->centsInteger($flightCents);
        $lodgingDays = $this->decimal->compare($commissionDays, '1') > 0
            ? $this->decimal->subtract($commissionDays, '1', 4)
            : '0.0000';

        $perDiemPesos = $this->decimal->multiply(
            $this->decimal->multiply($commissionDays, $perDiemUma, 8),
            $umaValue,
            12,
        );
        $lodgingPesos = $this->decimal->multiply(
            $this->decimal->multiply($lodgingDays, $lodgingUma, 8),
            $umaValue,
            12,
        );
        $perDiemCents = $this->decimal->centsHalfUp($perDiemPesos);
        $lodgingCents = $this->decimal->centsHalfUp($lodgingPesos);
        $totalCents = $this->decimal->centsInteger(
            $this->decimal->add(
                $this->decimal->add($perDiemCents, $lodgingCents, 0),
                $flightCents,
                0,
            ),
        );

        return new TravelCommissionCalculationResult(
            perDiemCents: $perDiemCents,
            lodgingCents: $lodgingCents,
            flightCents: $flightCents,
            totalCents: $totalCents,
        );
    }
}
