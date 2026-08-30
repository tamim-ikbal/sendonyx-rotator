<?php

use App\Support\Geo\MaxMindCountryDatabase;
use MaxMind\Db\Reader;
use Tests\TestCase;

// Unit tests do not boot the application, and the file this reads is named by
// configuration rather than fixed in the repository.
uses(TestCase::class);

/**
 * The database file is licensed and not committed, so this runs only where one
 * has been fetched. It exists to pin the one thing the adapter knows about the
 * file's shape: that the code lives at `country.iso_code`.
 */
beforeEach(function () {
    $path = config()->string('rotator.geo.database');

    if (! is_file($path)) {
        $this->markTestSkipped('No country database fetched. Run: php artisan rotator:geoip-update');
    }

    $this->database = new MaxMindCountryDatabase(new Reader($path));
});

test('reads the country an address belongs to', function () {
    expect($this->database->lookup('8.8.8.8'))->toBe('US');
});

test('reports no country for an address the file does not list', function () {
    expect($this->database->lookup('127.0.0.1'))->toBeNull();
});

test('reports no country rather than throwing for a malformed address', function () {
    expect($this->database->lookup('not an address'))->toBeNull();
});
