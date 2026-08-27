<?php

namespace App\Support\ApiDocs;

/**
 * One documented API endpoint.
 *
 * The uri is the router's own template, `{rotator}` placeholders included, so
 * `ApiDocsTest` can assert every documented endpoint still resolves to a route
 * that is actually registered. Documentation that drifts off the routes is
 * worse than none.
 */
final readonly class ApiEndpoint
{
    /**
     * @param  string  $uri  The router's uri template, without a leading slash.
     * @param  array<int, ApiParameter>  $parameters
     */
    public function __construct(
        public string $id,
        public string $method,
        public string $uri,
        public string $title,
        public string $summary,
        public array $parameters = [],
    ) {}

    /**
     * Reduce the endpoint to the props the docs page renders.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'uri' => $this->uri,
            'title' => $this->title,
            'summary' => $this->summary,
            'parameters' => array_map(
                static fn (ApiParameter $parameter): array => $parameter->toArray(),
                $this->parameters,
            ),
        ];
    }
}
