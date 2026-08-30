<?php

namespace App\Providers;

use App\Support\Geo\CountryDatabase;
use App\Support\Geo\MaxMindCountryDatabase;
use App\Support\Geo\NullCountryDatabase;
use App\Support\Rotation\CacheRotationStateStore;
use App\Support\Rotation\RedisRotationStateStore;
use App\Support\Rotation\RotationStateStore;
use App\Support\Rotation\RotatorCache;
use App\Support\Rotation\SmoothWeightedRoundRobin;
use App\Support\Rotation\VisitorIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use MaxMind\Db\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRotationStateStore();
        $this->registerRotatorCache();
        $this->registerVisitorIdentity();
        $this->registerCountryDatabase();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Bind the rotation state store selected by configuration.
     *
     * The redis driver advances the cursor with an atomic Lua script and is the
     * production choice. The cache driver runs the same algorithm in PHP so the
     * suite passes on machines and CI runners without a Redis server.
     */
    protected function registerRotationStateStore(): void
    {
        $this->app->singleton(RotationStateStore::class, function (): RotationStateStore {
            $ttl = config()->integer('rotator.wrr.ttl');

            if (config()->string('rotator.state_store') === 'redis') {
                return new RedisRotationStateStore(
                    $this->app->make(RedisFactory::class),
                    config()->string('rotator.wrr.connection'),
                    $ttl,
                );
            }

            return new CacheRotationStateStore(
                $this->app->make(CacheFactory::class)->store(config()->string('rotator.cache_store')),
                new SmoothWeightedRoundRobin,
                $ttl,
            );
        });
    }

    /**
     * Bind the snapshot cache the redirect route reads on every hit.
     */
    protected function registerRotatorCache(): void
    {
        $this->app->singleton(RotatorCache::class, fn (): RotatorCache => new RotatorCache(
            $this->app->make(CacheFactory::class),
            config()->string('rotator.cache_store'),
            config()->string('rotator.cache_key'),
            [
                config()->integer('rotator.cache_ttl.0'),
                config()->integer('rotator.cache_ttl.1'),
            ],
        ));
    }

    /**
     * Bind the pseudonymous visitor identity used by the redirect route.
     *
     * The application key is the HMAC secret, taken from the encrypter so the
     * base64 encoded form in the environment is decoded exactly once.
     */
    protected function registerVisitorIdentity(): void
    {
        $this->app->singleton(VisitorIdentity::class, function (): VisitorIdentity {
            /** @var string $key */
            $key = $this->app->make(Encrypter::class)->getKey();

            return new VisitorIdentity(
                $key,
                config()->string('rotator.cookie.name'),
                config()->integer('rotator.cookie.days'),
            );
        });
    }

    /**
     * Bind the country database the click recording job falls back to.
     *
     * The file is licensed and several megabytes, so it is fetched per
     * environment rather than committed, and a fresh checkout or a CI runner
     * legitimately has none. That is not an error: country then comes from the
     * CDN header alone and anything arriving another way is recorded without
     * one, which the statistics layer already reads as unclassified.
     *
     * An unreadable file is treated the same way rather than thrown, because a
     * half written monthly refresh must not stop a worker recording clicks.
     *
     * Bound as a singleton so a long running worker memory maps the file once
     * and every lookup after that is a tree walk. The flip side is that a
     * worker holds its open file across a refresh: `queue:restart` after one.
     */
    protected function registerCountryDatabase(): void
    {
        $this->app->singleton(CountryDatabase::class, function (): CountryDatabase {
            $database = config()->string('rotator.geo.database');

            if (! is_file($database)) {
                return new NullCountryDatabase;
            }

            try {
                return new MaxMindCountryDatabase(new Reader($database));
            } catch (InvalidDatabaseException) {
                return new NullCountryDatabase;
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
