<?php

namespace App\Http\Requests\TrafficRotator;

use App\Concerns\ResolvesRotatorRoute;
use App\Concerns\RotatorValidationRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateDestinationRequest extends FormRequest
{
    use ResolvesRotatorRoute, RotatorValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->rotator());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'required', ...$this->outboundUrlRules()],
            'weight' => ['sometimes', 'required', ...$this->destinationWeightRules()],
            'status' => ['sometimes', 'required', ...$this->destinationStatusRules()],
        ];
    }
}
