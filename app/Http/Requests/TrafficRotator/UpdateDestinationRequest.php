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
            // No `required` beside `sometimes` here, unlike the fields above:
            // sending an explicit null is the only way to detach a destination
            // from its plan or its customer.
            'plan_uid' => ['sometimes', ...$this->externalUidRules()],
            'customer_uid' => ['sometimes', ...$this->externalUidRules()],
            'weight' => ['sometimes', 'required', ...$this->destinationWeightRules()],
            'status' => ['sometimes', 'required', ...$this->destinationStatusRules()],
        ];
    }
}
