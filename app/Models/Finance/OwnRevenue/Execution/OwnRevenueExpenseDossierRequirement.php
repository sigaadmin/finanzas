<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_expense_dossier_id', 'own_revenue_expense_requirement_rule_id',
    'status', 'notes', 'evidence_document_id', 'exception_reason',
    'exception_evidence_document_id', 'acted_by', 'acted_at',
])]
class OwnRevenueExpenseDossierRequirement extends Model
{
    /** @use HasFactory<OwnRevenueExpenseDossierRequirementFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OwnRevenueExpenseRequirementStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueExpenseDossier, $this> */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseDossier::class, 'own_revenue_expense_dossier_id');
    }

    /** @return BelongsTo<OwnRevenueExpenseRequirementRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseRequirementRule::class, 'own_revenue_expense_requirement_rule_id');
    }

    /** @return BelongsTo<OwnRevenueExpenseDossierDocument, $this> */
    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseDossierDocument::class, 'evidence_document_id');
    }

    /** @return BelongsTo<OwnRevenueExpenseDossierDocument, $this> */
    public function exceptionEvidenceDocument(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseDossierDocument::class, 'exception_evidence_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
