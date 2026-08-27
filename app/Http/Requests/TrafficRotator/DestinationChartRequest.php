<?php

namespace App\Http\Requests\TrafficRotator;

use App\Concerns\ResolvesRotatorRoute;
use App\Enums\StatsRange;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DestinationChartRequest extends FormRequest
{
    use ResolvesRotatorRoute;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->rotator());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'range' => ['sometimes', Rule::enum(StatsRange::class)],
        ];
    }

    /**
     * Get the range the caller asked for.
     */
    public function statsRange(): StatsRange
    {
        return $this->enum('range', StatsRange::class) ?? StatsRange::LAST_30_DAYS;
    }
}
