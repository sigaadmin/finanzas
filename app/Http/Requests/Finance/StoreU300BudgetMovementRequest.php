<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreU300BudgetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'u300_budget_line_id' => ['required', 'integer', 'exists:u300_budget_lines,id'],
            'type' => ['required', 'string', Rule::in(['commitment', 'expense', 'reimbursement'])],
            'movement_date' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:255'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{u300_budget_line_id: int, type: string, movement_date: string, concept: string, document_reference: string|null, amount_cents: int}
     */
    public function movement(): array
    {
        $validated = $this->validated();

        return [
            'u300_budget_line_id' => (int) $validated['u300_budget_line_id'],
            'type' => (string) $validated['type'],
            'movement_date' => (string) $validated['movement_date'],
            'concept' => (string) $validated['concept'],
            'document_reference' => $validated['document_reference'] ?? null,
            'amount_cents' => (int) $validated['amount_cents'],
        ];
    }
}
