<?php

namespace App\Http\Requests\TrafficRotator;

use App\Concerns\ResolvesRotatorRoute;
use App\Concerns\RotatorValidationRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateRotatorRequest extends FormRequest
{
    use ResolvesRotatorRoute, RotatorValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorisation runs before validation, so a caller who does not own the
     * rotator gets the policy's 404 rather than a 422 that would confirm the
     * uuid resolves to something.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->rotator());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Every field is optional: the dashboard toggles status on its own, and
     * requiring the whole record back would make that a read-modify-write.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', ...$this->rotatorNameRules()],
            'slug' => ['sometimes', 'required', ...$this->rotatorSlugRules($this->rotator()->id)],
            'status' => ['sometimes', 'required', ...$this->rotatorStatusRules()],
            'default_destination_url' => ['sometimes', 'nullable', ...$this->outboundUrlRules()],
        ];
    }
}
