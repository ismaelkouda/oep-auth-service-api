<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/u', ' ', trim($this->input('name')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\p{M}\s\'’.-]+$/u'],
            'phone' => [
                'sometimes', 'string', 'regex:/^\+[1-9]\d{6,14}$/',
                Rule::unique('users', 'phone')->whereNull('deleted_at')->ignore($this->route('user')),
            ],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'profile_id' => ['sometimes', 'uuid', Rule::exists('profiles', 'id')->whereNot('code', 'citizen')],
            'organization_id' => ['sometimes', 'uuid'],
            'territory_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
