<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueImportOriginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['originable_type', 'originable_id', 'own_revenue_import_row_id', 'field_name'])]
class OwnRevenueImportOrigin extends Model
{
    /** @use HasFactory<OwnRevenueImportOriginFactory> */
    use HasFactory;

    /** @return MorphTo<Model, $this> */
    public function originable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<OwnRevenueImportRow, $this> */
    public function row(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportRow::class, 'own_revenue_import_row_id');
    }
}
