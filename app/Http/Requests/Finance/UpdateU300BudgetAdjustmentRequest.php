<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateU300BudgetAdjustmentRequest extends FormRequest
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
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.u300_action_id' => ['required', 'integer', 'exists:u300_actions,id'],
            'allocations.*.amount_cents' => ['required', 'integer', 'min:0'],
            'allocations.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array{u300_action_id: int, amount_cents: int, description: string|null}>
     */
    public function allocations(): array
    {
        return collect($this->validated('allocations'))
            ->map(fn (array $allocation): array => [
                'u300_action_id' => (int) $allocation['u300_action_id'],
                'amount_cents' => (int) $allocation['amount_cents'],
                'description' => $allocation['description'] ?? null,
            ])
            ->values()
            ->all();
    }
}
