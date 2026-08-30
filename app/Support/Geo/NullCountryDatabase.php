<?php

namespace App\Support\Geo;

/**
 * The database that is bound when no country database file is available.
 *
 * The file is several megabytes and licensed, so it is not committed: a fresh
 * checkout, CI, and any environment that has not fetched it yet all run without
 * one. Country then comes from the CDN header alone and every other hit records
 * a null country, which is exactly what the statistics layer already expects
 * from an unclassified row.
 */
final class NullCountryDatabase implements CountryDatabase
{
    /**
     * Look up the ISO 3166-1 alpha-2 code an address belongs to.
     */
    public function lookup(string $ipAddress): ?string
    {
        return null;
    }
}
