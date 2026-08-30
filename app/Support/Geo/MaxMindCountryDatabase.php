<?php

namespace App\Support\Geo;

use InvalidArgumentException;
use MaxMind\Db\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * A country database backed by a MaxMind DB (`.mmdb`) file.
 *
 * The file is memory mapped once when the reader is constructed and every
 * lookup after that is a walk down a binary search tree, so a long running
 * queue worker pays the open cost once and the lookups themselves cost
 * microseconds. Nothing leaves the machine.
 *
 * DB-IP Lite and MaxMind GeoLite2 both publish this format with the same
 * `country.iso_code` field, so either file works here unchanged.
 */
final class MaxMindCountryDatabase implements CountryDatabase
{
    public function __construct(private readonly Reader $reader) {}

    /**
     * Look up the ISO 3166-1 alpha-2 code an address belongs to.
     *
     * A click is never lost to this lookup. A malformed address, an address the
     * file has no record for, and a truncated or half written database file all
     * come back as an uncategorised click rather than a failed job, because the
     * click itself is the thing worth keeping.
     */
    public function lookup(string $ipAddress): ?string
    {
        try {
            $record = $this->reader->get($ipAddress);
        } catch (InvalidArgumentException|InvalidDatabaseException) {
            return null;
        }

        if (! is_array($record) || ! isset($record['country']) || ! is_array($record['country'])) {
            return null;
        }

        $isoCode = $record['country']['iso_code'] ?? null;

        return is_string($isoCode) ? $isoCode : null;
    }
}
