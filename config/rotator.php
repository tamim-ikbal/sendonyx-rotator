<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rotation State Store
    |--------------------------------------------------------------------------
    |
    | Which driver holds the smooth weighted round robin cursor. The redis
    | driver runs the algorithm as an atomic Lua script and is the production
    | choice. The cache driver runs the same algorithm in PHP and exists so the
    | suite passes on machines and CI runners without a Redis server.
    |
    */

    'state_store' => env('ROTATOR_STATE_STORE', 'redis'),

    'wrr' => [
        'connection' => env('ROTATOR_REDIS_CONNECTION', 'default'),
        'ttl' => (int) env('ROTATOR_WRR_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotator Snapshot Cache
    |--------------------------------------------------------------------------
    |
    | The redirect hot path reads the rotator and its active destinations from
    | cache rather than querying on every hit. Both observers flush this key.
    |
    */

    'cache_store' => env('ROTATOR_CACHE_STORE', env('CACHE_STORE', 'database')),
    'cache_key' => 'rotator:primary:v1',
    'cache_ttl' => [300, 3600],

    /*
    |--------------------------------------------------------------------------
    | Visitor Cookie
    |--------------------------------------------------------------------------
    */

    'cookie' => [
        'name' => env('ROTATOR_COOKIE_NAME', 'rotator_vid'),
        'days' => (int) env('ROTATOR_COOKIE_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Click Queue
    |--------------------------------------------------------------------------
    */

    'queue' => env('ROTATOR_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Visitor Country
    |--------------------------------------------------------------------------
    |
    | Country is resolved by the recording job, never on the redirect. A CDN in
    | front of the rotator states the country in a header it sets on the edge,
    | which the redirect passes through untouched; anything arriving without
    | one falls back to the local database file named here. That file is
    | licensed and several megabytes, so it is fetched rather than committed,
    | and an environment without it simply records no country.
    |
    */

    'geo' => [
        'header' => env('ROTATOR_COUNTRY_HEADER', 'CF-IPCountry'),
        'database' => env('ROTATOR_GEO_DATABASE', storage_path('app/geoip/dbip-country-lite.mmdb')),
    ],

];
