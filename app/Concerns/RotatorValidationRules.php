<?php

namespace App\Concerns;

use App\Enums\DestinationStatus;
use App\Enums\RotatorStatus;
use App\Models\TrafficRotator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The shape of a rotator and its destinations, shared by the write requests.
 *
 * None of these carry a presence rule. Creating a rotator and patching one
 * disagree about which fields are required but agree about what a valid value
 * looks like, so `required` and `sometimes` stay at the call site.
 */
trait RotatorValidationRules
{
    /**
     * Get the validation rules used to validate rotator names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function rotatorNameRules(): array
    {
        return ['string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate rotator slugs.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function rotatorSlugRules(?int $rotatorId = null): array
    {
        return [
            'string',
            'max:255',
            'alpha_dash',
            $rotatorId === null
                ? Rule::unique(TrafficRotator::class, 'slug')
                : Rule::unique(TrafficRotator::class, 'slug')->ignore($rotatorId),
        ];
    }

    /**
     * Get the validation rules used to validate rotator statuses.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function rotatorStatusRules(): array
    {
        return [Rule::enum(RotatorStatus::class)];
    }

    /**
     * Get the validation rules used to validate destination statuses.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function destinationStatusRules(): array
    {
        return [Rule::enum(DestinationStatus::class)];
    }

    /**
     * Get the validation rules used to validate an outbound url.
     *
     * The scheme is pinned rather than left to `url` alone, which accepts
     * javascript: and data: payloads. Every one of these values is eventually
     * handed to a visitor's browser in a Location header.
     *
     * The 2048 ceiling matches the column.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function outboundUrlRules(): array
    {
        return ['string', 'url:http,https', 'max:2048'];
    }

    /**
     * Get the validation rules used to validate an external identifier.
     *
     * `plan_uid` and `customer_uid` are opaque handles minted elsewhere, so
     * nothing is asserted about their shape beyond the column ceiling. They
     * are nullable on purpose: clearing one is how a destination stops being
     * attributed to a plan or a seat.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function externalUidRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate destination weights.
     *
     * The ceiling is the number of priority tiers the dashboard offers, not an
     * arbitrary bound: a weight outside 1-3 has nothing to render as.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function destinationWeightRules(): array
    {
        return ['integer', 'between:1,3'];
    }
}
