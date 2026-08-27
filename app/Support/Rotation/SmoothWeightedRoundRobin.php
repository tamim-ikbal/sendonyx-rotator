<?php

namespace App\Support\Rotation;

/**
 * Smooth weighted round robin, as used by nginx's upstream balancer.
 *
 * This class is the normative definition of the algorithm. The Lua script in
 * scripts/smooth_weighted_round_robin.lua mirrors it so the Redis driver can run
 * the same selection atomically, and the two are held together by a shared
 * dataset of expected sequences.
 */
final class SmoothWeightedRoundRobin
{
    /**
     * Advance the cursor by one selection.
     *
     * @param  array<int, int>  $weights  Destination id => weight, ordered by destination id ascending.
     * @param  array<int, mixed>  $currentWeights  Destination id => stored current weight, from an untyped store.
     * @return array{destinationId: int|null, currentWeights: array<int, int>}
     */
    public function advance(array $weights, array $currentWeights): array
    {
        if ($weights === []) {
            return ['destinationId' => null, 'currentWeights' => []];
        }

        $total = array_sum($weights);
        $current = $this->normalize($weights, $currentWeights);

        $winner = null;

        foreach ($weights as $id => $weight) {
            $current[$id] += $weight;

            if ($winner === null || $current[$id] > $current[$winner]) {
                $winner = $id;
            }
        }

        $current[$winner] -= $total;

        return ['destinationId' => $winner, 'currentWeights' => $current];
    }

    /**
     * Drop unknown entries, zero fill missing ones and restart a broken cycle.
     *
     * The current weights always sum to exactly zero: each pass adds the total
     * across all destinations and subtracts the total from one. Any other sum
     * means the stored state was truncated or tampered with, so the cycle is
     * restarted rather than continued from a meaningless position.
     *
     * @param  array<int, int>  $weights
     * @param  array<int, mixed>  $currentWeights
     * @return array<int, int>
     */
    private function normalize(array $weights, array $currentWeights): array
    {
        $current = [];

        foreach ($weights as $id => $weight) {
            $stored = $currentWeights[$id] ?? 0;

            $current[$id] = is_int($stored) ? $stored : 0;
        }

        if (array_sum($current) !== 0) {
            return array_map(static fn (): int => 0, $current);
        }

        return $current;
    }
}
