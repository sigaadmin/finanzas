<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_expense_dossier_id', 'from_status', 'to_status', 'reason', 'actor_id', 'occurred_at',
])]
class OwnRevenueExpenseDossierTransition extends Model
{
    /** @use HasFactory<OwnRevenueExpenseDossierTransitionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => OwnRevenueExpenseDossierStatus::class,
            'to_status' => OwnRevenueExpenseDossierStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueExpenseDossier, $this> */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseDossier::class, 'own_revenue_expense_dossier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
