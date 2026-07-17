<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueBudgetModificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_budget_id', 'type', 'source_line_id', 'destination_line_id',
    'amount_cents', 'reason', 'source_balance_before_cents', 'source_balance_after_cents',
    'destination_balance_before_cents', 'destination_balance_after_cents', 'recorded_by', 'recorded_at',
])]
class OwnRevenueBudgetModification extends Model
{
    /** @use HasFactory<OwnRevenueBudgetModificationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => OwnRevenueBudgetModificationType::class,
            'amount_cents' => 'integer',
            'source_balance_before_cents' => 'integer',
            'source_balance_after_cents' => 'integer',
            'destination_balance_before_cents' => 'integer',
            'destination_balance_after_cents' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueModifiedBudgetLine, $this> */
    public function sourceLine(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueModifiedBudgetLine::class, 'source_line_id');
    }

    /** @return BelongsTo<OwnRevenueModifiedBudgetLine, $this> */
    public function destinationLine(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueModifiedBudgetLine::class, 'destination_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
