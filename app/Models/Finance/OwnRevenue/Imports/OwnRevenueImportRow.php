<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['own_revenue_import_file_id', 'sheet_name', 'row_number', 'row_kind', 'row_hash', 'source_payload', 'normalized_payload'])]
class OwnRevenueImportRow extends Model
{
    /** @use HasFactory<OwnRevenueImportRowFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['source_payload' => 'array', 'normalized_payload' => 'array'];
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'own_revenue_import_file_id');
    }

    /** @return HasMany<OwnRevenueImportIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(OwnRevenueImportIssue::class);
    }

    /** @return HasMany<OwnRevenueImportOrigin, $this> */
    public function origins(): HasMany
    {
        return $this->hasMany(OwnRevenueImportOrigin::class);
    }
}
