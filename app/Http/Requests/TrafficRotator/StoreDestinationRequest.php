<?php

namespace App\Http\Requests\TrafficRotator;

use App\Concerns\ResolvesRotatorRoute;
use App\Concerns\RotatorValidationRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDestinationRequest extends FormRequest
{
    use ResolvesRotatorRoute, RotatorValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Adding a destination changes what the rotator sends traffic to, so the
     * ability checked is `update` on the parent.
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
            'url' => ['required', ...$this->outboundUrlRules()],
            'weight' => ['sometimes', ...$this->destinationWeightRules()],
            'status' => ['sometimes', ...$this->destinationStatusRules()],
        ];
    }
}
