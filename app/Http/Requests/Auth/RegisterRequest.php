<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
            // Lettres, accents, espaces, apostrophes (' et ’), points et traits d'union.
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\p{M}\s\'’.-]+$/u'],
        ];
    }
}
