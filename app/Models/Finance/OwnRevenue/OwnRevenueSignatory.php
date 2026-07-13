<?php

namespace App\Models\Finance\OwnRevenue;

use Database\Factories\Finance\OwnRevenue\OwnRevenueSignatoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['own_revenue_budget_id', 'role_key', 'name', 'position', 'academic_degree', 'sort_order'])]
class OwnRevenueSignatory extends Model
{
    /** @use HasFactory<OwnRevenueSignatoryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<OwnRevenueBudget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }
}
