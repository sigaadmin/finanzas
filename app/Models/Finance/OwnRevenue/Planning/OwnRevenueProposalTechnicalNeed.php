<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_proposal_id', 'own_revenue_budget_id', 'own_revenue_activity_id',
    'source_technical_sheet_need_id', 'expense_classification_id', 'stable_key',
    'specific_item_code', 'specific_item_name', 'chapter_code', 'chapter_name', 'sequence',
    'quantity', 'unit', 'description', 'unit_price_cents', 'reference_amount_cents',
    'budget_amount_cents', 'budget_month', 'impact_on_goals', 'region_code', 'region_name', 'sort_order',
])]
class OwnRevenueProposalTechnicalNeed extends Model
{
    /** @use HasFactory<OwnRevenueProposalTechnicalNeedFactory> */
    use HasFactory;

    protected $attributes = [
        'region_code' => '02-001',
        'region_name' => 'Felipe Carrillo Puerto',
        'sort_order' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<OwnRevenueProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueProposal::class, 'own_revenue_proposal_id');
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<OwnRevenueTechnicalSheetNeed, $this> */
    public function sourceTechnicalSheetNeed(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueTechnicalSheetNeed::class, 'source_technical_sheet_need_id');
    }

    /** @return BelongsTo<ExpenseClassification, $this> */
    public function expenseClassification(): BelongsTo
    {
        return $this->belongsTo(ExpenseClassification::class);
    }

    /** @return MorphMany<OwnRevenuePlanningCorrection, $this> */
    public function corrections(): MorphMany
    {
        return $this->morphMany(OwnRevenuePlanningCorrection::class, 'correctable');
    }
}
