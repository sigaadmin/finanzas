<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'origin', 'normalized_origin', 'destination',
    'normalized_destination', 'one_way_kilometers', 'additional_kilometers', 'is_active', 'sort_order',
])]
class OwnRevenueRoute extends Model
{
    /** @use HasFactory<OwnRevenueRouteFactory> */
    use HasFactory;

    protected $attributes = ['additional_kilometers' => 0, 'is_active' => true, 'sort_order' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'one_way_kilometers' => 'decimal:4',
            'additional_kilometers' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return HasMany<OwnRevenueProposalFuelNeed, $this> */
    public function fuelNeeds(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalFuelNeed::class);
    }
}
