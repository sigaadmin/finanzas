<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueAbpreJustificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_import_file_id', 'chapter_code', 'chapter_name', 'specific_item_code',
    'specific_item_name', 'budget_program_code', 'budget_program_name', 'component_code', 'component_name',
    'goals_impact', 'justification', 'sort_order',
])]
class OwnRevenueAbpreJustification extends Model
{
    /** @use HasFactory<OwnRevenueAbpreJustificationFactory> */
    use HasFactory;

    protected $attributes = ['sort_order' => 0];

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'own_revenue_import_file_id');
    }

    /** @return MorphMany<OwnRevenueImportOrigin, $this> */
    public function origins(): MorphMany
    {
        return $this->morphMany(OwnRevenueImportOrigin::class, 'originable');
    }
}
