<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_proposal_id', 'own_revenue_budget_id', 'own_revenue_activity_id',
    'source_fuel_plan_id', 'own_revenue_route_id', 'stable_key', 'commission_date_label',
    'operational_month', 'budget_month', 'reason', 'vehicle_model', 'kilometers_per_liter',
    'outbound_origin', 'outbound_destination', 'outbound_kilometers', 'return_origin',
    'return_destination', 'return_kilometers', 'additional_kilometers', 'total_kilometers',
    'liters', 'fuel_price', 'mathematical_amount_cents', 'rounded_amount_cents',
    'budget_amount_cents', 'rounding_difference_cents', 'override_justification', 'sort_order',
])]
class OwnRevenueProposalFuelNeed extends Model
{
    /** @use HasFactory<OwnRevenueProposalFuelNeedFactory> */
    use HasFactory;

    protected $attributes = ['sort_order' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kilometers_per_liter' => 'decimal:4',
            'outbound_kilometers' => 'decimal:4',
            'return_kilometers' => 'decimal:4',
            'additional_kilometers' => 'decimal:4',
            'total_kilometers' => 'decimal:4',
            'liters' => 'decimal:4',
            'fuel_price' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<OwnRevenueProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueProposal::class, 'own_revenue_proposal_id');
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<OwnRevenueFuelPlan, $this> */
    public function sourceFuelPlan(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueFuelPlan::class, 'source_fuel_plan_id');
    }

    /** @return BelongsTo<OwnRevenueRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueRoute::class, 'own_revenue_route_id');
    }

    /** @return MorphMany<OwnRevenuePlanningCorrection, $this> */
    public function corrections(): MorphMany
    {
        return $this->morphMany(OwnRevenuePlanningCorrection::class, 'correctable');
    }
}
