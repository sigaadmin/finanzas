<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_modified_budget_line_id', 'sequence_number',
    'folio', 'status', 'concept', 'amount_cents', 'purchase_responsibility',
    'external_reference', 'notes', 'requested_by', 'sufficiency_requested_at',
    'sufficiency_confirmed_by', 'sufficiency_confirmed_at',
])]
class OwnRevenueExpenseDossier extends Model
{
    /** @use HasFactory<OwnRevenueExpenseDossierFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'status' => OwnRevenueExpenseDossierStatus::class,
            'amount_cents' => 'integer',
            'purchase_responsibility' => OwnRevenuePurchaseResponsibility::class,
            'sufficiency_requested_at' => 'datetime',
            'sufficiency_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueModifiedBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueModifiedBudgetLine::class, 'own_revenue_modified_budget_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function sufficiencyConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sufficiency_confirmed_by');
    }

    /** @return HasMany<OwnRevenueExpenseDossierTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(OwnRevenueExpenseDossierTransition::class);
    }
}
