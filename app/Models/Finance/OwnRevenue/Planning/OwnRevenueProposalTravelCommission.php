<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission as ImportedTravelCommission;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_proposal_id', 'own_revenue_budget_id', 'own_revenue_activity_id',
    'source_travel_commission_id', 'own_revenue_travel_destination_id', 'stable_key',
    'commission_date_label', 'operational_month', 'budget_month', 'reason', 'destination',
    'food_zone', 'lodging_zone', 'uma_value', 'flight_amount_cents',
    'participants_amount_cents', 'total_amount_cents', 'override_justification', 'sort_order',
])]
class OwnRevenueProposalTravelCommission extends Model
{
    /** @use HasFactory<OwnRevenueProposalTravelCommissionFactory> */
    use HasFactory;

    protected $attributes = [
        'flight_amount_cents' => 0,
        'participants_amount_cents' => 0,
        'total_amount_cents' => 0,
        'sort_order' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['uma_value' => 'decimal:4'];
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

    /** @return BelongsTo<ImportedTravelCommission, $this> */
    public function sourceTravelCommission(): BelongsTo
    {
        return $this->belongsTo(ImportedTravelCommission::class, 'source_travel_commission_id');
    }

    /** @return BelongsTo<OwnRevenueTravelDestination, $this> */
    public function travelDestination(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueTravelDestination::class, 'own_revenue_travel_destination_id');
    }

    /** @return HasMany<OwnRevenueProposalTravelParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalTravelParticipant::class);
    }

    /** @return MorphMany<OwnRevenuePlanningCorrection, $this> */
    public function corrections(): MorphMany
    {
        return $this->morphMany(OwnRevenuePlanningCorrection::class, 'correctable');
    }
}
