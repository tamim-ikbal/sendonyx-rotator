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

];
