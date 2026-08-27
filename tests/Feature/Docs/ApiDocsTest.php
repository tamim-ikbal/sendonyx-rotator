<?php

use App\Models\User;
use App\Support\ApiDocs\ApiEndpoint;
use App\Support\ApiDocs\ApiParameter;
use App\Support\ApiDocs\ApiReference;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Get every endpoint in the reference, flattened out of its group.
 *
 * @return Collection<int, ApiEndpoint>
 */
function documentedEndpoints(): Collection
{
    return collect(app(ApiReference::class)->groups())
        ->flatMap(fn (array $group): array => $group['endpoints']);
}

/**
 * Get every registered route that the API serves.
 *
 * @return Collection<int, RegisteredRoute>
 */
function registeredApiRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RegisteredRoute $route): bool => str_starts_with($route->uri(), 'api/'))
        ->values();
}

test('guests are redirected to the login page', function () {
    $this->get(route('docs.index'))->assertRedirect(route('login'));
});

test('renders the reference for a signed in user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('docs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index')
            ->has('groups', 2)
            ->has('groups.0.endpoints.0', fn (Assert $endpoint) => $endpoint
                ->where('method', 'GET')
                ->where('uri', 'api/rotators')
                ->etc(),
            ),
        );
});

test('documents the base url of the host the request arrived on', function () {
    $this->actingAs(User::factory()->create())
        ->get('http://rotator.example.com/docs')
        ->assertInertia(fn (Assert $page) => $page
            ->where('baseUrl', 'http://rotator.example.com')
            ->etc(),
        );
});

test('documents no endpoint that the api does not register', function () {
    $routes = registeredApiRoutes();

    $undocumented = documentedEndpoints()
        ->reject(fn (ApiEndpoint $endpoint): bool => $routes->contains(
            fn (RegisteredRoute $route): bool => $route->uri() === $endpoint->uri
                && in_array($endpoint->method, $route->methods(), true),
        ))
        ->map(fn (ApiEndpoint $endpoint): string => "{$endpoint->method} /{$endpoint->uri}")
        ->values()
        ->all();

    expect($undocumented)->toBe([]);
});

test('documents every endpoint the api registers', function () {
    $documented = documentedEndpoints();

    $missing = registeredApiRoutes()
        ->reject(fn (RegisteredRoute $route): bool => $documented->contains(
            fn (ApiEndpoint $endpoint): bool => $endpoint->uri === $route->uri(),
        ))
        ->map(fn (RegisteredRoute $route): string => "/{$route->uri()}")
        ->unique()
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('declares a path parameter for every placeholder in a documented uri', function () {
    $undeclared = documentedEndpoints()
        ->flatMap(function (ApiEndpoint $endpoint): array {
            preg_match_all('/\{(\w+)\}/', $endpoint->uri, $matches);

            $declared = collect($endpoint->parameters)
                ->filter(fn (ApiParameter $parameter): bool => $parameter->in === ApiParameter::IN_PATH)
                ->map(fn (ApiParameter $parameter): string => $parameter->name)
                ->all();

            return collect($matches[1])
                ->diff($declared)
                ->map(fn (string $placeholder): string => "{$endpoint->id}: {$placeholder}")
                ->all();
        })
        ->values()
        ->all();

    expect($undeclared)->toBe([]);
});
