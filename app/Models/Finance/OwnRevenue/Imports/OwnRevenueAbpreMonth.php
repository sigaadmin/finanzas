<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueAbpreMonthFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['own_revenue_abpre_line_id', 'month', 'amount_cents'])]
class OwnRevenueAbpreMonth extends Model
{
    /** @use HasFactory<OwnRevenueAbpreMonthFactory> */
    use HasFactory;

    /** @return BelongsTo<OwnRevenueAbpreLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueAbpreLine::class, 'own_revenue_abpre_line_id');
    }

    /** @return MorphMany<OwnRevenueImportOrigin, $this> */
    public function origins(): MorphMany
    {
        return $this->morphMany(OwnRevenueImportOrigin::class, 'originable');
    }
}
