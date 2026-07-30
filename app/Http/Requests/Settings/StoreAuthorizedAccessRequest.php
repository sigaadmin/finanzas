<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuthorizedAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'ends_with:@crenfcp.edu.mx', 'max:255', 'unique:authorized_accesses,email'],
            'role' => ['required', Rule::enum(UserRole::class)->except(UserRole::Owner)],
            'is_active' => ['sometimes', 'boolean'],
            'can_operate_ventanilla' => ['sometimes', 'boolean'],
            'can_operate_u300' => ['sometimes', 'boolean'],
            'can_operate_own_revenue' => ['sometimes', 'boolean'],
        ];
    }
}
