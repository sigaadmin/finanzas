<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenuePlanningCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'own_revenue_proposal_id', 'correctable_type', 'correctable_id', 'field',
    'old_value', 'new_value', 'justification', 'corrected_by', 'corrected_at',
])]
class OwnRevenuePlanningCorrection extends Model
{
    /** @use HasFactory<OwnRevenuePlanningCorrectionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['corrected_at' => 'datetime'];
    }

    /** @return BelongsTo<OwnRevenueProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueProposal::class, 'own_revenue_proposal_id');
    }

    /** @return MorphTo<Model, $this> */
    public function correctable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
