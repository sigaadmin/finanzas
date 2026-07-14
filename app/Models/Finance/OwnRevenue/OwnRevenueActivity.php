<?php

namespace App\Models\Finance\OwnRevenue;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use Database\Factories\Finance\OwnRevenue\OwnRevenueActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['own_revenue_budget_id', 'code', 'name', 'sort_order'])]
class OwnRevenueActivity extends Model
{
    /** @use HasFactory<OwnRevenueActivityFactory> */
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

    /** @return HasMany<OwnRevenueWorkSheetLine, $this> */
    public function workSheetLines(): HasMany
    {
        return $this->hasMany(OwnRevenueWorkSheetLine::class);
    }
}
