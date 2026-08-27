<?php

namespace App\Support\Rotation;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

/**
 * Production rotation state, advanced by an atomic Redis Lua script.
 *
 * The entire read, decide and write cycle runs inside one EVAL, so concurrent
 * requests cannot lose an update. The script mirrors SmoothWeightedRoundRobin
 * and both are asserted against the same golden sequence dataset.
 */
final class RedisRotationStateStore implements RotationStateStore
{
    private static ?string $script = null;

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly string $connection,
        private readonly int $ttlSeconds,
    ) {}

    /**
     * Advance the rotator's cursor and return the winning destination id.
     *
     * @param  array<int, int>  $weights
     */
    public function advance(int $rotatorId, array $weights): ?int
    {
        if ($weights === []) {
            return null;
        }

        $key = RotationStateKey::for($rotatorId, $weights);

        // The first two entries are KEYS, so they inherit the client's key
        // prefix. The unprefixed key repeats in ARGV because it is stored as a
        // set member rather than used as a key, and members are never prefixed.
        $arguments = [
            $key,
            RotationStateKey::index($rotatorId),
            $key,
            (string) $this->ttlSeconds,
        ];

        foreach ($weights as $id => $weight) {
            $arguments[] = (string) $id;
            $arguments[] = (string) $weight;
        }

        $winner = $this->connection()->command('eval', [
            $this->script(),
            $arguments,
            2,
        ]);

        return is_numeric($winner) ? (int) $winner : null;
    }

    /**
     * Discard every stored cursor for the rotator.
     *
     * The index set holds the unprefixed key names, so they can be deleted
     * directly without SCAN and without the client prefix being applied twice.
     */
    public function forget(int $rotatorId): void
    {
        $connection = $this->connection();
        $index = RotationStateKey::index($rotatorId);

        $keys = $connection->command('smembers', [$index]);

        if (is_array($keys) && $keys !== []) {
            $connection->command('del', array_values($keys));
        }

        $connection->command('del', [$index]);
    }

    /**
     * Resolve the configured Redis connection.
     */
    private function connection(): Connection
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * Read the Lua script from disk once per process.
     */
    private function script(): string
    {
        return self::$script ??= (string) file_get_contents(
            __DIR__.'/scripts/smooth_weighted_round_robin.lua',
        );
    }
}
