<?php

use App\Support\Geo\CountryDatabase;
use App\Support\Geo\CountryResolver;
use App\Support\Geo\NullCountryDatabase;

/**
 * A country database that answers from a fixed map of addresses.
 *
 * @param  array<string, string>  $countries
 */
function countryDatabase(array $countries = []): CountryDatabase
{
    return new class($countries) implements CountryDatabase
    {
        /**
         * @param  array<string, string>  $countries
         */
        public function __construct(private readonly array $countries) {}

        public function lookup(string $ipAddress): ?string
        {
            return $this->countries[$ipAddress] ?? null;
        }
    };
}

test('prefers the country the cdn already resolved on the edge', function () {
    $resolver = new CountryResolver(countryDatabase(['203.0.113.9' => 'FR']));

    expect($resolver->resolve('DE', '203.0.113.9'))->toBe('DE');
});

test('falls back to the database for a hit that did not come through the cdn', function () {
    $resolver = new CountryResolver(countryDatabase(['203.0.113.9' => 'FR']));

    expect($resolver->resolve(null, '203.0.113.9'))->toBe('FR');
});

test('normalises the case a source reports a country in', function () {
    $resolver = new CountryResolver(countryDatabase(['203.0.113.9' => 'fr']));

    expect($resolver->resolve('de', null))->toBe('DE')
        ->and($resolver->resolve(null, '203.0.113.9'))->toBe('FR');
});

test('reads the placeholders a cdn sends for no country as no country', function (string $header) {
    $resolver = new CountryResolver(countryDatabase(['203.0.113.9' => 'FR']));

    expect($resolver->resolve($header, null))->toBeNull()
        ->and($resolver->resolve($header, '203.0.113.9'))->toBe('FR');
})->with([
    'unknown' => 'XX',
    'tor exit node' => 'T1',
]);

test('discards a header value that is not a country code', function (string $header) {
    $resolver = new CountryResolver(new NullCountryDatabase);

    expect($resolver->resolve($header, '203.0.113.9'))->toBeNull();
})->with([
    'a country name' => 'Germany',
    'an alpha-3 code' => 'DEU',
    'one letter' => 'D',
    'not letters' => 'D3',
    'a fragment of sql' => "D'--",
]);

test('records no country when neither source places the visitor', function () {
    $resolver = new CountryResolver(new NullCountryDatabase);

    expect($resolver->resolve(null, '203.0.113.9'))->toBeNull()
        ->and($resolver->resolve(null, null))->toBeNull();
});
