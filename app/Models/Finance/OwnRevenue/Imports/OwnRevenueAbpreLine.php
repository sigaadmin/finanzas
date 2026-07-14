<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueAbpreLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_import_file_id', 'expense_classification_id', 'responsible_unit_code',
    'responsible_unit_name', 'budget_program_code', 'budget_program_name', 'component_code', 'component_name',
    'official_activity_code', 'official_activity_name', 'region_code', 'region_name', 'specific_expense_concept_code',
    'specific_item_code', 'annual_amount_cents', 'sort_order',
])]
class OwnRevenueAbpreLine extends Model
{
    /** @use HasFactory<OwnRevenueAbpreLineFactory> */
    use HasFactory;

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

    /** @return BelongsTo<ExpenseClassification, $this> */
    public function expenseClassification(): BelongsTo
    {
        return $this->belongsTo(ExpenseClassification::class);
    }

    /** @return HasMany<OwnRevenueAbpreMonth, $this> */
    public function months(): HasMany
    {
        return $this->hasMany(OwnRevenueAbpreMonth::class);
    }

    /** @return MorphMany<OwnRevenueImportOrigin, $this> */
    public function origins(): MorphMany
    {
        return $this->morphMany(OwnRevenueImportOrigin::class, 'originable');
    }
}
