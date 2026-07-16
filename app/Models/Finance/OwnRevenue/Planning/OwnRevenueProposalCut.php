<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueProposalCutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_proposal_id', 'own_revenue_activity_id', 'target_type', 'target_id',
    'stable_key', 'specific_item_code', 'budget_month', 'available_amount_cents',
    'amount_cents', 'created_by',
])]
class OwnRevenueProposalCut extends Model
{
    /** @use HasFactory<OwnRevenueProposalCutFactory> */
    use HasFactory;

    /** @return BelongsTo<OwnRevenueProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueProposal::class, 'own_revenue_proposal_id');
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
