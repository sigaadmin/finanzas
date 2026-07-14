<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_import_file_id', 'own_revenue_activity_id', 'expense_classification_id',
    'activity_code', 'activity_name', 'item_name', 'specific_item_code', 'region_code', 'region_name',
    'annual_amount_cents', 'sort_order',
])]
class OwnRevenueWorkSheetLine extends Model
{
    /** @use HasFactory<OwnRevenueWorkSheetLineFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'region_code' => '02-001',
        'region_name' => 'Felipe Carrillo Puerto',
        'sort_order' => 0,
    ];

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

    /** @return BelongsTo<ExpenseClassification, $this> */
    public function expenseClassification(): BelongsTo
    {
        return $this->belongsTo(ExpenseClassification::class);
    }

    /** @return HasMany<OwnRevenueWorkSheetMonth, $this> */
    public function months(): HasMany
    {
        return $this->hasMany(OwnRevenueWorkSheetMonth::class);
    }

    /** @return MorphMany<OwnRevenueImportOrigin, $this> */
    public function origins(): MorphMany
    {
        return $this->morphMany(OwnRevenueImportOrigin::class, 'originable');
    }
}
