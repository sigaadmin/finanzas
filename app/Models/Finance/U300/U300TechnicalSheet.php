<?php

namespace App\Models\Finance\U300;

use Database\Factories\Finance\U300\U300TechnicalSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'u300_budget_line_id',
    'item_name',
    'objective',
    'work_description',
    'technical_specs',
    'goods_profile',
    'beneficiaries',
    'scheduled_date',
    'deliverables',
    'delivery_location',
    'supervisor',
    'payment_terms',
])]
class U300TechnicalSheet extends Model
{
    /** @use HasFactory<U300TechnicalSheetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goods_profile' => 'array',
        ];
    }

    /**
     * @return BelongsTo<U300BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(U300BudgetLine::class, 'u300_budget_line_id');
    }
}
