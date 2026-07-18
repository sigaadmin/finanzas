<?php

namespace App\Services\Finance\OwnRevenue\Execution;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Support\Facades\Gate;

class OwnRevenueExecutionViewData
{
    public function __construct(private readonly OwnRevenueBudgetBalance $balances) {}

    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $lines = $budget->modifiedBudgetLines()
            ->withSum('incomingModifications', 'amount_cents')
            ->withSum('outgoingModifications', 'amount_cents')
            ->orderBy('chapter_code')
            ->orderBy('specific_item_code')
            ->orderBy('month')
            ->get();
        $lineData = $lines->map(fn (OwnRevenueModifiedBudgetLine $line): array => $this->line($line))->all();

        return [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'region_code' => $budget->region_code,
                'region_name' => $budget->region_name,
            ],
            'summary' => [
                'initial_amount_cents' => (string) array_sum(array_column($lineData, 'initial_amount_cents')),
                'modified_amount_cents' => (string) array_sum(array_column($lineData, 'modified_amount_cents')),
                'reserved_amount_cents' => (string) array_sum(array_column($lineData, 'reserved_amount_cents')),
                'committed_amount_cents' => (string) array_sum(array_column($lineData, 'committed_amount_cents')),
                'paid_amount_cents' => (string) array_sum(array_column($lineData, 'paid_amount_cents')),
                'available_amount_cents' => (string) array_sum(array_column($lineData, 'available_amount_cents')),
            ],
            'lines' => $lineData,
            'classifications' => $this->classifications($budget),
            'modifications' => $this->modifications($budget),
            'expense_dossiers' => $this->expenseDossiers($budget),
            'requirement_rules' => $budget->expenseRequirementRules()
                ->where('is_active', true)
                ->orderBy('target_status')
                ->orderBy('title')
                ->get()
                ->map(fn ($rule): array => [
                    'id' => $rule->id,
                    'title' => $rule->title,
                    'description' => $rule->description,
                    'target_status' => $rule->target_status->value,
                    'purchase_responsibility' => $rule->purchase_responsibility?->value,
                    'chapter_code' => $rule->chapter_code,
                    'specific_item_code' => $rule->specific_item_code,
                    'minimum_amount_cents' => $rule->minimum_amount_cents === null ? null : (string) $rule->minimum_amount_cents,
                    'requires_evidence' => $rule->requires_evidence,
                ])->all(),
            'permissions' => [
                'manage' => Gate::allows('manageExecution', $budget),
                'create_expense_dossier' => Gate::allows('createExpenseDossier', $budget),
                'request_expense_sufficiency' => Gate::allows('requestExpenseSufficiency', $budget),
                'confirm_expense_sufficiency' => Gate::allows('confirmExpenseSufficiency', $budget),
                'manage_expense_purchase' => Gate::allows('manageExpensePurchase', $budget),
                'authorize_expense_payment' => Gate::allows('authorizeExpensePayment', $budget),
                'cancel_expense_dossier' => Gate::allows('cancelExpenseDossier', $budget),
                'reject_expense_dossier' => Gate::allows('rejectExpenseDossier', $budget),
                'complete_expense_requirement' => Gate::allows('completeExpenseRequirement', $budget),
                'except_expense_requirement' => Gate::allows('exceptExpenseRequirement', $budget),
                'manage_expense_requirement_rules' => Gate::allows('manageExpenseRequirementRules', $budget),
            ],
        ];
    }

    /** @return array<string, int|string> */
    private function line(OwnRevenueModifiedBudgetLine $line): array
    {
        $incoming = (int) ($line->incoming_modifications_sum_amount_cents ?? 0);
        $outgoing = (int) ($line->outgoing_modifications_sum_amount_cents ?? 0);
        $modified = $this->balances->modifiedCents($line);
        $reserved = $this->balances->reservedCents($line);
        $committed = $this->balances->committedCents($line);
        $paid = $this->balances->paidCents($line);

        return [
            'id' => $line->id,
            'chapter_code' => $line->chapter_code,
            'chapter_name' => $line->chapter_name,
            'specific_item_code' => $line->specific_item_code,
            'specific_item_name' => $line->specific_item_name,
            'month' => $line->month,
            'initial_amount_cents' => (string) $line->getRawOriginal('initial_amount_cents'),
            'incoming_amount_cents' => (string) $incoming,
            'outgoing_amount_cents' => (string) $outgoing,
            'modified_amount_cents' => (string) $modified,
            'reserved_amount_cents' => (string) $reserved,
            'committed_amount_cents' => (string) $committed,
            'paid_amount_cents' => (string) $paid,
            'available_amount_cents' => (string) $this->balances->availableCents($line),
        ];
    }

    /** @return list<array<string, int|string>> */
    private function classifications(OwnRevenueBudget $budget): array
    {
        return ExpenseClassification::query()
            ->where('fiscal_year', $budget->fiscal_year)
            ->orderBy('specific_item_code')
            ->get(['id', 'chapter_code', 'chapter_name', 'specific_item_code', 'specific_item_name'])
            ->map(fn (ExpenseClassification $classification): array => [
                'id' => $classification->id,
                'chapter_code' => $classification->chapter_code,
                'chapter_name' => $classification->chapter_name,
                'specific_item_code' => $classification->specific_item_code,
                'specific_item_name' => $classification->specific_item_name,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function modifications(OwnRevenueBudget $budget): array
    {
        return $budget->budgetModifications()
            ->with(['sourceLine:id,specific_item_code,specific_item_name,month', 'destinationLine:id,specific_item_code,specific_item_name,month', 'recordedBy:id,name'])
            ->latest('recorded_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn ($modification): array => [
                'id' => $modification->id,
                'type' => $modification->type->value,
                'amount_cents' => (string) $modification->getRawOriginal('amount_cents'),
                'reason' => $modification->reason,
                'source' => [
                    'specific_item_code' => $modification->sourceLine->specific_item_code,
                    'specific_item_name' => $modification->sourceLine->specific_item_name,
                    'month' => $modification->sourceLine->month,
                ],
                'destination' => [
                    'specific_item_code' => $modification->destinationLine->specific_item_code,
                    'specific_item_name' => $modification->destinationLine->specific_item_name,
                    'month' => $modification->destinationLine->month,
                ],
                'recorded_by_name' => $modification->recordedBy->name,
                'recorded_at' => $modification->recorded_at?->toISOString(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function expenseDossiers(OwnRevenueBudget $budget): array
    {
        return $budget->expenseDossiers()
            ->with([
                'budgetLine:id,specific_item_code,specific_item_name,month',
                'requester:id,name',
                'latestTransition.actor:id,name',
                'documents:id,own_revenue_expense_dossier_id,original_name,mime_type,size_bytes,uploaded_at',
                'requirements.rule:id,title,description,target_status,requires_evidence',
                'requirements.actor:id,name',
                'requirements.evidenceDocument:id,original_name',
                'requirements.exceptionEvidenceDocument:id,original_name',
            ])
            ->latest('id')
            ->get()
            ->map(fn ($dossier): array => [
                'id' => $dossier->id,
                'folio' => $dossier->folio,
                'status' => $dossier->status->value,
                'concept' => $dossier->concept,
                'amount_cents' => (string) $dossier->getRawOriginal('amount_cents'),
                'purchase_responsibility' => $dossier->purchase_responsibility->value,
                'external_reference' => $dossier->external_reference,
                'purchase_reference' => $dossier->purchase_reference,
                'payment_request_reference' => $dossier->payment_request_reference,
                'finance_authorization_reference' => $dossier->finance_authorization_reference,
                'budget_office_authorization_reference' => $dossier->budget_office_authorization_reference,
                'payment_reference' => $dossier->payment_reference,
                'notes' => $dossier->notes,
                'line' => [
                    'specific_item_code' => $dossier->budgetLine->specific_item_code,
                    'specific_item_name' => $dossier->budgetLine->specific_item_name,
                    'month' => $dossier->budgetLine->month,
                ],
                'requested_by_name' => $dossier->requester->name,
                'sufficiency_requested_at' => $dossier->sufficiency_requested_at?->toISOString(),
                'sufficiency_confirmed_at' => $dossier->sufficiency_confirmed_at?->toISOString(),
                'purchase_started_at' => $dossier->purchase_started_at?->toISOString(),
                'payment_requested_at' => $dossier->payment_requested_at?->toISOString(),
                'finance_authorized_at' => $dossier->finance_authorized_at?->toISOString(),
                'budget_office_authorized_at' => $dossier->budget_office_authorized_at?->toISOString(),
                'paid_at' => $dossier->paid_at?->toISOString(),
                'latest_transition' => $dossier->latestTransition === null ? null : [
                    'from_status' => $dossier->latestTransition->from_status?->value,
                    'to_status' => $dossier->latestTransition->to_status->value,
                    'reason' => $dossier->latestTransition->reason,
                    'actor_name' => $dossier->latestTransition->actor->name,
                    'occurred_at' => $dossier->latestTransition->occurred_at?->toISOString(),
                ],
                'documents' => $dossier->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'uploaded_at' => $document->uploaded_at?->toISOString(),
                ])->all(),
                'requirements' => $dossier->requirements->map(fn ($requirement): array => [
                    'id' => $requirement->id,
                    'title' => $requirement->rule->title,
                    'description' => $requirement->rule->description,
                    'target_status' => $requirement->rule->target_status->value,
                    'requires_evidence' => $requirement->rule->requires_evidence,
                    'status' => $requirement->status->value,
                    'notes' => $requirement->notes,
                    'exception_reason' => $requirement->exception_reason,
                    'actor_name' => $requirement->actor?->name,
                    'acted_at' => $requirement->acted_at?->toISOString(),
                    'evidence_name' => $requirement->evidenceDocument?->original_name,
                    'exception_evidence_name' => $requirement->exceptionEvidenceDocument?->original_name,
                ])->all(),
            ])->all();
    }
}
