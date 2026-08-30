<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Behind a CDN the visitor's address arrives in X-Forwarded-For and
    | Request::ip() returns an edge address until the proxy in front is
    | trusted. For the rotator that is not cosmetic: an untrusted proxy puts a
    | datacentre country on every click and collapses every cookie blocked
    | visitor behind one edge node into a single fingerprint.
    |
    | Nothing is trusted by default, because trusting a proxy that is not there
    | lets anyone forge the header by reaching the origin directly. Set
    | TRUSTED_PROXIES to a comma separated list of the CDN's own ranges,
    | published at cloudflare.com/ips-v4 and /ips-v6.
    |
    | Not '*', even when the origin is firewalled to the CDN. Cloudflare
    | appends to X-Forwarded-For rather than replacing it, so a visitor sending
    | their own header arrives as "forged, real". Symfony discards every
    | trusted hop and takes the last one standing, which is the real address
    | when only the CDN ranges are trusted and the forged one when everything
    | is. Locking the origin down does not close that; naming the ranges does.
    |
    | Read by Illuminate\Http\Middleware\TrustProxies, which is already in the
    | default global middleware stack.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
