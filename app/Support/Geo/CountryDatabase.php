<?php

namespace App\Support\Geo;

/**
 * A local database that answers which country an IP address sits in.
 *
 * The rotator resolves country from a CDN header wherever one is present, so
 * this is the fallback for traffic that reaches the origin another way: a hit
 * that bypassed the CDN, local development, or a second CDN added later. It is
 * an interface so that path stays testable without a binary database file in
 * the repository, and so an absent file degrades to nulls rather than throwing.
 */
interface CountryDatabase
{
    /**
     * Look up the ISO 3166-1 alpha-2 code an address belongs to.
     *
     * Returns null for a private, reserved or simply unlisted address. An
     * unresolvable address is normal traffic, not an error.
     */
    public function lookup(string $ipAddress): ?string;
}
