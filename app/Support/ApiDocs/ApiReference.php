<?php

namespace App\Support\ApiDocs;

use App\Enums\DestinationStatus;
use App\Enums\RotatorStatus;
use App\Enums\StatsRange;

/**
 * The catalogue the API docs page renders.
 *
 * It mirrors `routes/api.php`, and the parameter rules mirror the form
 * requests those routes run. Enum options are read off the enums rather than
 * retyped, so adding a case cannot leave the docs describing the old set.
 */
final readonly class ApiReference
{
    /**
     * Get the documented endpoints, in the order the page lists them.
     *
     * @return array<int, array{name: string, description: string, endpoints: array<int, ApiEndpoint>}>
     */
    public function groups(): array
    {
        return [
            [
                'name' => 'Rotators',
                'description' => 'A rotator owns the destinations traffic is split across. You only ever see your own.',
                'endpoints' => $this->rotatorEndpoints(),
            ],
            [
                'name' => 'Destinations',
                'description' => 'Destinations are always addressed through their rotator. One belonging to another rotator answers 404.',
                'endpoints' => $this->destinationEndpoints(),
            ],
        ];
    }

    /**
     * @return array<int, ApiEndpoint>
     */
    private function rotatorEndpoints(): array
    {
        return [
            new ApiEndpoint(
                'rotators.index',
                'GET',
                'api/rotators',
                'List rotators',
                'Paginated, oldest first, and scoped to the rotators you own. Each row carries a destinations_count.',
            ),
            new ApiEndpoint(
                'rotators.show',
                'GET',
                'api/rotators/{rotator}',
                'Get a rotator',
                'Returns the rotator with its destinations embedded, plus total_clicks and unique_visitors for its whole lifetime. This is the only way to enumerate destinations: there is no destination index.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                ],
            ),
            new ApiEndpoint(
                'rotators.traffic-by-plans',
                'GET',
                'api/rotators/{rotator}/traffic-by-plans',
                'Get traffic by plan',
                'Lifetime clicks totalled per plan_uid, busiest first. Attribution is stamped on each click when it is recorded, so these are the clicks a plan earned: moving a destination to another plan does not move its history. Clicks recorded while their destination had no plan_uid are left out rather than collected under a null key, and so are the fallback hits that had no destination at all.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                ],
            ),
            new ApiEndpoint(
                'rotators.traffic-by-members',
                'GET',
                'api/rotators/{rotator}/traffic-by-members',
                'Get traffic by member',
                'Lifetime clicks totalled per customer_uid, busiest first. Stamped at record time and excluded on a null exactly as the plan breakdown is.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                ],
            ),
        ];
    }

    /**
     * @return array<int, ApiEndpoint>
     */
    private function destinationEndpoints(): array
    {
        return [
            new ApiEndpoint(
                'destinations.store',
                'POST',
                'api/rotators/{rotator}/destinations',
                'Add a destination',
                'Returns 201 with the new destination. It inherits the rotator and its owner from the url, so neither can be named in the body.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                    ApiParameter::body('url', 'url', true, 'Where the visitor is sent. http or https only, up to 2048 characters.', 'https://example.com/offer-a'),
                    ApiParameter::body('plan_uid', 'string', false, 'The plan this destination is provisioned under, up to 255 characters. Opaque to the rotator: it is stamped onto each click the destination serves, and only grouped by. Optional.', 'plan_9f2a'),
                    ApiParameter::body('customer_uid', 'string', false, 'The customer this destination belongs to, up to 255 characters. Optional.', 'cus_4b7e'),
                    ApiParameter::body('weight', 'integer', false, 'The priority tier, 1 to 3. Traffic is split in proportion to weight. Defaults to 1.', '1'),
                    ApiParameter::body('status', 'enum', false, 'A paused destination takes no traffic. Defaults to active.', null, $this->valuesOf(DestinationStatus::cases())),
                ],
            ),
            new ApiEndpoint(
                'destinations.show',
                'GET',
                'api/rotators/{rotator}/destinations/{destination}',
                'Get a destination',
                'The destination has to belong to the rotator in the url, or the response is a 404.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                    ApiParameter::path('destination', 'The destination uuid.'),
                ],
            ),
            new ApiEndpoint(
                'destinations.update',
                'PATCH',
                'api/rotators/{rotator}/destinations/{destination}',
                'Update a destination',
                'Pausing a destination takes it out of the rotation on the next request; the rotation cursor is reset so the remaining weights stay in proportion.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                    ApiParameter::path('destination', 'The destination uuid.'),
                    ApiParameter::body('url', 'url', false, 'http or https only, up to 2048 characters.', 'https://example.com/offer-b'),
                    ApiParameter::body('plan_uid', 'string', false, 'Send null to detach the destination from its plan. Clicks already recorded keep the plan they were stamped with.', 'plan_9f2a'),
                    ApiParameter::body('customer_uid', 'string', false, 'Send null to detach the destination from its customer. Clicks already recorded keep the customer they were stamped with.', 'cus_4b7e'),
                    ApiParameter::body('weight', 'integer', false, 'The priority tier, 1 to 3.', '2'),
                    ApiParameter::body('status', 'enum', false, 'A paused destination takes no traffic.', null, $this->valuesOf(DestinationStatus::cases())),
                ],
            ),
            new ApiEndpoint(
                'destinations.stats',
                'GET',
                'api/rotators/{rotator}/destinations/{destination}/stats',
                'Get destination stats',
                'The headline figures behind a destination panel: clicks, unique visitors, the rotator traffic they came out of, and how long the destination has been live. Bot traffic is excluded, so these figures match what the dashboard calls views.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                    ApiParameter::path('destination', 'The destination uuid.'),
                    ApiParameter::query('range', 'enum', 'The reporting window. Defaults to all_time, which reports lifetime totals and no comparison against a preceding period.', null, $this->valuesOf(StatsRange::cases())),
                ],
            ),
            new ApiEndpoint(
                'destinations.chart',
                'GET',
                'api/rotators/{rotator}/destinations/{destination}/chart',
                'Get destination analytics',
                'The chart series and the tiles beside it: click-through rate, average daily clicks, and the leading country and device. The headline figures live on the stats endpoint.',
                [
                    ApiParameter::path('rotator', 'The rotator uuid.'),
                    ApiParameter::path('destination', 'The destination uuid.'),
                    ApiParameter::query('range', 'enum', 'The reporting window, which also sets the bucket size of the series. Defaults to last_30_days.', null, $this->valuesOf(StatsRange::cases())),
                ],
            ),
        ];
    }

    /**
     * Read the backing values off a set of enum cases.
     *
     * @param  array<int, RotatorStatus|DestinationStatus|StatsRange>  $cases
     * @return array<int, string>
     */
    private function valuesOf(array $cases): array
    {
        return array_map(static fn (RotatorStatus|DestinationStatus|StatsRange $case): string => $case->value, $cases);
    }
}
