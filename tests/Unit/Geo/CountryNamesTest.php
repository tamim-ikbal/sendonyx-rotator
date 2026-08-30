<?php

use App\Support\Geo\CountryNames;
use Tests\TestCase;

// Unit tests do not boot the application, and the list this reads is named by
// configuration.
uses(TestCase::class);

beforeEach(function () {
    CountryNames::flush();
});

afterEach(function () {
    CountryNames::flush();
});

test('resolves a stored code to the name of its country', function () {
    expect(CountryNames::name('DE'))->toBe('Germany');
});

test('resolves a code whatever case and padding it arrives in', function () {
    expect(CountryNames::name(' de '))->toBe('Germany');
});

test('leaves an unclassified click unnamed', function () {
    expect(CountryNames::name(null))->toBeNull()
        ->and(CountryNames::name(' '))->toBeNull();
});

test('reports a code the list does not carry as itself', function () {
    expect(CountryNames::name('ZZ'))->toBe('ZZ');
});

test('reports codes as themselves when the list is missing', function () {
    config()->set('rotator.geo.countries', storage_path('app/geo/no-such-list.php'));

    expect(CountryNames::all())->toBe([])
        ->and(CountryNames::name('DE'))->toBe('DE');
});
