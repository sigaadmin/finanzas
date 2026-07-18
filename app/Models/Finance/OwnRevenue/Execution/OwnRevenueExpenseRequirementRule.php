<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'title', 'description', 'target_status',
    'purchase_responsibility', 'chapter_code', 'specific_item_code',
    'minimum_amount_cents', 'requires_evidence', 'is_active', 'created_by',
])]
class OwnRevenueExpenseRequirementRule extends Model
{
    /** @use HasFactory<OwnRevenueExpenseRequirementRuleFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_status' => OwnRevenueExpenseDossierStatus::class,
            'purchase_responsibility' => OwnRevenuePurchaseResponsibility::class,
            'minimum_amount_cents' => 'integer',
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<OwnRevenueExpenseDossierRequirement, $this> */
    public function dossierRequirements(): HasMany
    {
        return $this->hasMany(OwnRevenueExpenseDossierRequirement::class);
    }
}
