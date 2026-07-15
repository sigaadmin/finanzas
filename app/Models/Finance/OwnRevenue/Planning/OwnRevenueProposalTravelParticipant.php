<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission as ImportedTravelCommission;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_proposal_travel_commission_id', 'own_revenue_proposal_id',
    'own_revenue_budget_id', 'own_revenue_activity_id', 'source_travel_commission_id',
    'own_revenue_travel_rate_id', 'stable_key', 'person_name', 'position', 'commission_days',
    'per_diem_uma', 'lodging_uma', 'per_diem_amount_cents', 'lodging_amount_cents',
    'total_amount_cents', 'sort_order',
])]
class OwnRevenueProposalTravelParticipant extends Model
{
    /** @use HasFactory<OwnRevenueProposalTravelParticipantFactory> */
    use HasFactory;

    protected $attributes = ['sort_order' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'commission_days' => 'decimal:4',
            'per_diem_uma' => 'decimal:4',
            'lodging_uma' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<OwnRevenueProposalTravelCommission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueProposalTravelCommission::class, 'own_revenue_proposal_travel_commission_id');
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

    /** @return BelongsTo<OwnRevenueTravelRate, $this> */
    public function travelRate(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueTravelRate::class, 'own_revenue_travel_rate_id');
    }

    /** @return MorphMany<OwnRevenuePlanningCorrection, $this> */
    public function corrections(): MorphMany
    {
        return $this->morphMany(OwnRevenuePlanningCorrection::class, 'correctable');
    }
}
