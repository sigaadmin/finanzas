<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_initial_budget_id', 'expense_classification_id',
    'chapter_code', 'chapter_name', 'specific_item_code', 'specific_item_name',
    'month', 'initial_amount_cents',
])]
class OwnRevenueModifiedBudgetLine extends Model
{
    /** @use HasFactory<OwnRevenueModifiedBudgetLineFactory> */
    use HasFactory;

    protected $attributes = ['initial_amount_cents' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'integer', 'initial_amount_cents' => 'integer'];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueInitialBudget, $this> */
    public function initialBudget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueInitialBudget::class, 'own_revenue_initial_budget_id');
    }

    /** @return BelongsTo<ExpenseClassification, $this> */
    public function expenseClassification(): BelongsTo
    {
        return $this->belongsTo(ExpenseClassification::class);
    }

    /** @return HasMany<OwnRevenueBudgetModification, $this> */
    public function outgoingModifications(): HasMany
    {
        return $this->hasMany(OwnRevenueBudgetModification::class, 'source_line_id');
    }

    /** @return HasMany<OwnRevenueBudgetModification, $this> */
    public function incomingModifications(): HasMany
    {
        return $this->hasMany(OwnRevenueBudgetModification::class, 'destination_line_id');
    }
}
