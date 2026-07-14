<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetMonthFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['own_revenue_work_sheet_line_id', 'month', 'amount_cents'])]
class OwnRevenueWorkSheetMonth extends Model
{
    /** @use HasFactory<OwnRevenueWorkSheetMonthFactory> */
    use HasFactory;

    /** @return BelongsTo<OwnRevenueWorkSheetLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueWorkSheetLine::class, 'own_revenue_work_sheet_line_id');
    }

    /** @return MorphMany<OwnRevenueImportOrigin, $this> */
    public function origins(): MorphMany
    {
        return $this->morphMany(OwnRevenueImportOrigin::class, 'originable');
    }
}
