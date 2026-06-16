<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateU300FederalVerdictRequest extends FormRequest
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
            'federal_authorized_total_cents' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:u300_requested_items,id'],
            'items.*.approved_amount_cents' => ['nullable', 'integer', 'min:0'],
            'items.*.approved_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return list<array{id: int, approved_amount_cents: int|null, approved_percentage: float|null}>
     */
    public function verdictItems(): array
    {
        return collect($this->validated('items'))
            ->map(fn (array $item): array => [
                'id' => (int) $item['id'],
                'approved_amount_cents' => isset($item['approved_amount_cents'])
                    ? (int) $item['approved_amount_cents']
                    : null,
                'approved_percentage' => isset($item['approved_percentage'])
                    ? (float) $item['approved_percentage']
                    : null,
            ])
            ->values()
            ->all();
    }

    public function federalAuthorizedTotalCents(): ?int
    {
        $value = $this->validated('federal_authorized_total_cents');

        return isset($value) ? (int) $value : null;
    }
}
