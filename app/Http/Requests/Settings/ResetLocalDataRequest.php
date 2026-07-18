<?php

namespace App\Http\Requests\Settings;

use App\Enums\Settings\LocalDataResetScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResetLocalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation' => [
                'required',
                'string',
                Rule::in([$this->scope()->confirmationPhrase()]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Escriba la frase de confirmación para continuar.',
            'confirmation.in' => 'La frase de confirmación no coincide.',
        ];
    }

    public function scope(): LocalDataResetScope
    {
        $scope = LocalDataResetScope::tryFrom((string) $this->route('scope'));
        abort_if($scope === null, 404);

        return $scope;
    }
}
