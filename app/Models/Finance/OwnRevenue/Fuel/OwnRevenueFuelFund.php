<?php

namespace App\Models\Finance\OwnRevenue\Fuel;

use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Fuel\OwnRevenueFuelFundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'source_expense_dossier_id', 'acquired_amount_cents',
    'opened_by', 'opened_at',
])]
class OwnRevenueFuelFund extends Model
{
    /** @use HasFactory<OwnRevenueFuelFundFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['acquired_amount_cents' => 'integer', 'opened_at' => 'datetime'];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueExpenseDossier, $this> */
    public function sourceDossier(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseDossier::class, 'source_expense_dossier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return HasMany<OwnRevenueFuelCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(OwnRevenueFuelCommission::class);
    }
}
