<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\ChargeConceptStatus;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentProcedureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentProcedure::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student' => ['required', 'array'],
            'student.siga_student_id' => ['required', 'string', 'max:255'],
            'student.matricula' => ['nullable', 'string', 'max:255'],
            'student.name' => ['required', 'string', 'max:255'],
            'student.program' => ['nullable', 'string', 'max:255'],
            'student.grade' => ['nullable', 'string', 'max:255'],
            'student.group' => ['nullable', 'string', 'max:255'],
            'student.academic_status' => ['nullable', 'string', 'max:255'],
            'concept_ids' => ['required_without:items', 'array', 'min:1'],
            'concept_ids.*' => [
                'integer',
                Rule::exists(ChargeConcept::class, 'id')
                    ->where('status', ChargeConceptStatus::Active->value),
            ],
            'items' => ['required_without:concept_ids', 'array', 'min:1'],
            'items.*.charge_concept_id' => [
                'required',
                'integer',
                Rule::exists(ChargeConcept::class, 'id')
                    ->where('status', ChargeConceptStatus::Active->value),
            ],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return list<array{charge_concept_id: int, quantity: int}>
     */
    public function paymentItems(): array
    {
        $validated = $this->validated();

        if (isset($validated['items'])) {
            return collect($validated['items'])
                ->map(fn (array $item): array => [
                    'charge_concept_id' => (int) $item['charge_concept_id'],
                    'quantity' => (int) ($item['quantity'] ?? 1),
                ])
                ->values()
                ->all();
        }

        return collect($validated['concept_ids'] ?? [])
            ->map(fn (int $conceptId): array => [
                'charge_concept_id' => $conceptId,
                'quantity' => 1,
            ])
            ->values()
            ->all();
    }
}
