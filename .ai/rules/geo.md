---
paths:
  - 'app/Support/Geo/**'
---

# Geo

## Country comes from the CDN header first, the local file second, and never from the redirect
`CountryResolver` reads the CDN's `CF-IPCountry` header before it touches the database, because the edge already did the work. `RedirectController` only *collects* that header — it cannot be read anywhere else — and `RecordRotatorClick` does the resolving. No third-party geo API is called per hit; that was the point (2026-08-31, user's call: an API bills per redirect).

The `.mmdb` is CC-BY DB-IP Lite, ~8MB, fetched by `rotator:geoip-update` and **not committed**. A missing or corrupt file binds `NullCountryDatabase`, so country falls back to the header alone and the column stays null — the stats layer already reads null as unclassified. Never make an absent file throw.

`TRUSTED_PROXIES` (`config/trustedproxy.php`) is empty by default. Behind a CDN it must be set, or `Request::ip()` is an edge address and every click geolocates to a datacentre while every cookie-blocked visitor behind one edge node collapses into a single fingerprint.

The header is visitor-controlled on any request that bypassed the CDN, and `visitor_country` is a `char(2)` that reaches a GROUP BY. `CountryResolver::normalised()` is the only guard; keep the two-letter check and the `XX`/`T1` rejection there.
