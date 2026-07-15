<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_import_file_id', 'own_revenue_activity_id', 'source_row_id',
    'expense_classification_id', 'specific_item_code', 'specific_item_name', 'chapter_code', 'chapter_name',
    'sequence', 'quantity', 'unit', 'description', 'region_code', 'region_name',
    'amount_cents', 'budget_month', 'sort_order',
])]
class OwnRevenueTechnicalSheetNeed extends Model
{
    /** @use HasFactory<OwnRevenueTechnicalSheetNeedFactory> */
    use HasFactory;

    protected $attributes = ['region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto', 'sort_order' => 0];

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

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<OwnRevenueImportRow, $this> */
    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportRow::class, 'source_row_id');
    }

    /** @return BelongsTo<ExpenseClassification, $this> */
    public function expenseClassification(): BelongsTo
    {
        return $this->belongsTo(ExpenseClassification::class);
    }

    /** @return MorphMany<OwnRevenueActivityAssignment, $this> */
    public function activityAssignments(): MorphMany
    {
        return $this->morphMany(OwnRevenueActivityAssignment::class, 'assignable');
    }
}
