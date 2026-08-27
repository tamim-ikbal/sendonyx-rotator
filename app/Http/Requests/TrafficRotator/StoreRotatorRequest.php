<?php

namespace App\Http\Requests\TrafficRotator;

use App\Concerns\RotatorValidationRules;
use App\Models\TrafficRotator;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreRotatorRequest extends FormRequest
{
    use RotatorValidationRules;

    /**
     * Deny a second rotator before the request is validated.
     *
     * Authorising here rather than in the controller keeps the one rotator
     * limit on the same footing as ownership: the refusal lands before
     * validation, so a rejected request never answers with a 422 instead.
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', TrafficRotator::class);
    }

    /**
     * Derive the slug from the name when the caller did not supply one.
     *
     * A derived slug still has to clear the unique rule. Quietly appending a
     * suffix would hand back a rotator addressed by something the caller never
     * asked for, so a collision is reported rather than resolved.
     */
    protected function prepareForValidation(): void
    {
        if ($this->missing('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->value())]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', ...$this->rotatorNameRules()],
            'slug' => ['required', ...$this->rotatorSlugRules()],
            'status' => ['sometimes', ...$this->rotatorStatusRules()],
            'default_destination_url' => ['sometimes', 'nullable', ...$this->outboundUrlRules()],
        ];
    }
}
