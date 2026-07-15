<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueTravelDestinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'destination', 'normalized_destination',
    'food_zone', 'lodging_zone', 'is_active',
])]
class OwnRevenueTravelDestination extends Model
{
    /** @use HasFactory<OwnRevenueTravelDestinationFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return HasMany<OwnRevenueProposalTravelCommission, $this> */
    public function travelCommissions(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalTravelCommission::class);
    }
}
