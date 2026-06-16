<?php

namespace App\Models\Finance\U300;

use App\Models\User;
use Database\Factories\Finance\U300\U300BudgetMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'u300_budget_line_id',
    'recorded_by',
    'type',
    'movement_date',
    'concept',
    'document_reference',
    'amount_cents',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
])]
class U300BudgetMovement extends Model
{
    /** @use HasFactory<U300BudgetMovementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<U300BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(U300BudgetLine::class, 'u300_budget_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'movement_date' => 'date',
        ];
    }
}
