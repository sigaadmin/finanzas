<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateU300CogConversionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('operate-finance') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.id' => ['required', 'integer', 'exists:u300_actions,id'],
            'actions.*.justification' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer', 'exists:u300_budget_lines,id'],
            'lines.*.u300_action_id' => ['required', 'integer', 'exists:u300_actions,id'],
            'lines.*.amount_cents' => ['required', 'integer', 'min:1'],
            'lines.*.expense_classification_code' => ['nullable', 'string', 'max:10'],
            'lines.*.exercise_month' => ['nullable', 'string', Rule::in(['AGO', 'SEP', 'OCT', 'NOV', 'DIC'])],
        ];
    }

    /**
     * @return list<array{id: int, justification: string|null}>
     */
    public function actions(): array
    {
        return collect($this->validated('actions'))
            ->map(fn (array $action): array => [
                'id' => (int) $action['id'],
                'justification' => filled($action['justification'] ?? null)
                    ? trim((string) $action['justification'])
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int|null, u300_action_id: int, amount_cents: int, expense_classification_code: string|null, exercise_month: string|null}>
     */
    public function lines(): array
    {
        return collect($this->validated('lines'))
            ->map(fn (array $line): array => [
                'id' => isset($line['id']) ? (int) $line['id'] : null,
                'u300_action_id' => (int) $line['u300_action_id'],
                'amount_cents' => (int) $line['amount_cents'],
                'expense_classification_code' => filled($line['expense_classification_code'] ?? null)
                    ? trim((string) $line['expense_classification_code'])
                    : null,
                'exercise_month' => $line['exercise_month'] ?? null,
            ])
            ->values()
            ->all();
    }
}
