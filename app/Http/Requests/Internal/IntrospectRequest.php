<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class IntrospectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
