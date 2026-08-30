<?php

namespace App\Support\Geo;

/**
 * Decides which country a click is recorded against.
 *
 * A CDN in front of the rotator has already done this work on the edge and
 * states the answer in a header, so that is consulted first and costs a string
 * comparison. Only traffic arriving without one reaches the local database.
 */
final readonly class CountryResolver
{
    /**
     * The two character values a CDN sends when it has no country to report.
     *
     * Cloudflare uses `XX` for an address it cannot place and `T1` for a Tor
     * exit node. Both are well formed enough to survive the shape checks below
     * and would otherwise show up in the dashboard as leading countries.
     */
    private const NOT_A_COUNTRY = ['XX', 'T1'];

    public function __construct(private CountryDatabase $database) {}

    /**
     * Resolve the country code to store on a click.
     */
    public function resolve(?string $cdnCountry, ?string $ipAddress): ?string
    {
        $fromHeader = $this->normalised($cdnCountry);

        if ($fromHeader !== null) {
            return $fromHeader;
        }

        if ($ipAddress === null) {
            return null;
        }

        return $this->normalised($this->database->lookup($ipAddress));
    }

    /**
     * Reduce a candidate to a storable country code, or to null.
     *
     * The header is visitor controlled on any request that did not come through
     * the CDN, and `visitor_country` is a char(2) that reaches a GROUP BY in the
     * statistics queries. Anything that is not two letters is discarded here
     * rather than at the insert.
     */
    private function normalised(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $country = strtoupper(trim($country));

        if (strlen($country) !== 2 || ! ctype_alpha($country)) {
            return null;
        }

        if (in_array($country, self::NOT_A_COUNTRY, true)) {
            return null;
        }

        return $country;
    }
}
