<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueTravelRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'position', 'normalized_position', 'food_zone',
    'lodging_zone', 'per_diem_uma', 'lodging_uma', 'is_fallback', 'is_active',
])]
class OwnRevenueTravelRate extends Model
{
    /** @use HasFactory<OwnRevenueTravelRateFactory> */
    use HasFactory;

    protected $attributes = ['is_fallback' => false, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'per_diem_uma' => 'decimal:4',
            'lodging_uma' => 'decimal:4',
            'is_fallback' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return HasMany<OwnRevenueProposalTravelParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalTravelParticipant::class);
    }
}
