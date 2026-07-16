<?php

namespace App\Services\Finance\OwnRevenue\Planning;

final readonly class TechnicalNeedCalculationResult
{
    public function __construct(public string $referenceCents) {}
}
