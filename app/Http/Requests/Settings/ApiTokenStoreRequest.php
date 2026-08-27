<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApiTokenStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The name is only ever shown back to its owner, so it exists to tell one
     * token from another rather than to identify anything.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
