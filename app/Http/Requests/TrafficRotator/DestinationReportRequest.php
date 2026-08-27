<?php

namespace App\Http\Requests\TrafficRotator;

use App\Concerns\ResolvesRotatorRoute;
use App\Enums\StatsRange;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The shared shape of a destination reporting request.
 *
 * Both reports read the same `range` filter off the same enum; they differ
 * only in what a caller who omits it gets back. Keeping the rule in one place
 * is what stops the two endpoints accepting different windows.
 */
abstract class DestinationReportRequest extends FormRequest
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
        return $this->enum('range', StatsRange::class) ?? $this->defaultRange();
    }

    /**
     * Get the range this report falls back to when the caller names none.
     */
    abstract protected function defaultRange(): StatsRange;
}
