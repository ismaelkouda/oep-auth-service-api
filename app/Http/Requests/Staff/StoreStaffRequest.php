<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('email'))) {
            $merge['email'] = Str::lower(trim($this->input('email')));
        }

        if (is_string($this->input('name'))) {
            $merge['name'] = preg_replace('/\s+/u', ' ', trim($this->input('name')));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\p{M}\s\'’.-]+$/u'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'role' => ['required', Rule::enum(UserRole::class)],
            // Un agent ne peut pas porter le profil Usager.
            'profile_id' => ['required', 'uuid', Rule::exists('profiles', 'id')->whereNot('code', 'citizen')],
            // Identifiants du service Paramétrage : seul le format est vérifié ici.
            'organization_id' => ['required', 'uuid'],
            'territory_id' => ['nullable', 'uuid'],
        ];
    }
}
