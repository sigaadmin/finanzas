<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use Illuminate\Support\Str;

class OwnRevenueActivityGroupKey
{
    public function forTechnicalSheetNeed(OwnRevenueTechnicalSheetNeed $need): string
    {
        return $need->specific_item_code.'|'.$this->normalize($need->description);
    }

    public function forFuelPlan(OwnRevenueFuelPlan $plan): string
    {
        return $this->normalize($plan->reason);
    }

    public function forTravelCommission(OwnRevenueTravelCommission $commission): string
    {
        return $this->normalize($commission->reason);
    }

    public function hash(OwnRevenueImportFormat $format, string $groupKey): string
    {
        return hash('sha256', $format->value.'|'.$groupKey);
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')->ascii()->squish()->upper()->toString();
    }
}
