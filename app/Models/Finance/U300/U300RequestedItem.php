<?php

namespace App\Models\Finance\U300;

use Database\Factories\Finance\U300\U300RequestedItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'u300_action_id',
    'u300_budget_version_id',
    'expense_concept',
    'expense_item',
    'period',
    'quantity',
    'unit_price_cents',
    'total_cents',
    'approved_amount_cents',
    'approved_percentage',
    'sort_order',
])]
class U300RequestedItem extends Model
{
    /** @use HasFactory<U300RequestedItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_percentage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<U300Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(U300Action::class, 'u300_action_id');
    }

    /**
     * @return BelongsTo<U300BudgetVersion, $this>
     */
    public function budgetVersion(): BelongsTo
    {
        return $this->belongsTo(U300BudgetVersion::class, 'u300_budget_version_id');
    }
}
