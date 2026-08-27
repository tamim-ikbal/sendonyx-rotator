<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use Laravel\Fortify\Features;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Skip the test unless a Redis server is reachable.
     *
     * CI runs with no service containers, so every test touching Redis has to
     * opt out cleanly rather than fail the suite.
     */
    protected function skipUnlessRedisIsAvailable(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The phpredis extension is not installed.');
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable $exception) {
            $this->markTestSkipped('Redis is not reachable: '.$exception->getMessage());
        }

        // Guard against ever flushing a real database from a test run. Every
        // connection a test can reach has to be pinned, not just the default:
        // the cache connection is a separate database and defaults to the one
        // the developer's own application is using.
        foreach (['default', 'cache'] as $connection) {
            if (config()->string("database.redis.{$connection}.database") !== '15') {
                $this->markTestSkipped("Redis tests require the [{$connection}] connection on database 15.");
            }
        }
    }
}
