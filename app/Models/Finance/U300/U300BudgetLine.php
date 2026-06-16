<?php

namespace App\Models\Finance\U300;

use App\Models\Finance\ExpenseClassification;
use Database\Factories\Finance\U300\U300BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'u300_budget_version_id',
    'u300_action_id',
    'expense_classification_id',
    'amount_cents',
    'exercise_month',
    'description',
    'justification',
    'sort_order',
])]
class U300BudgetLine extends Model
{
    /** @use HasFactory<U300BudgetLineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<U300BudgetVersion, $this>
     */
    public function budgetVersion(): BelongsTo
    {
        return $this->belongsTo(U300BudgetVersion::class, 'u300_budget_version_id');
    }

    /**
     * @return BelongsTo<U300Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(U300Action::class, 'u300_action_id');
    }

    /**
     * @return BelongsTo<ExpenseClassification, $this>
     */
    public function expenseClassification(): BelongsTo
    {
        return $this->belongsTo(ExpenseClassification::class);
    }

    /**
     * @return HasOne<U300TechnicalSheet, $this>
     */
    public function technicalSheet(): HasOne
    {
        return $this->hasOne(U300TechnicalSheet::class);
    }

    /**
     * @return HasMany<U300BudgetMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(U300BudgetMovement::class);
    }
}
