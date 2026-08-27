<?php

use App\Support\Rotation\SmoothWeightedRoundRobin;

/**
 * Run the algorithm for the given number of selections.
 *
 * @param  array<int, int>  $weights
 * @return array{0: array<int, int|null>, 1: array<int, int>}
 */
function rotate(array $weights, int $picks): array
{
    $algorithm = new SmoothWeightedRoundRobin;
    $state = [];
    $sequence = [];

    for ($i = 0; $i < $picks; $i++) {
        $result = $algorithm->advance($weights, $state);
        $sequence[] = $result['destinationId'];
        $state = $result['currentWeights'];
    }

    return [$sequence, $state];
}

test('it produces the expected sequence', function (array $weights, int $picks, array $expected) {
    [$sequence] = rotate($weights, $picks);

    expect($sequence)->toBe($expected);
})->with('smooth weighted round robin sequences');

test('it selects each destination exactly its weight per cycle', function (array $weights, int $picks, array $expected) {
    [$sequence] = rotate($weights, array_sum($weights));

    $counts = array_count_values(array_map(intval(...), $sequence));
    ksort($counts);
    ksort($weights);

    expect($counts)->toBe($weights);
})->with('smooth weighted round robin sequences');

test('it returns the state to zero after a full cycle', function (array $weights) {
    [, $state] = rotate($weights, array_sum($weights));

    expect(array_sum($state))->toBe(0)
        ->and($state)->each->toBe(0);
})->with('smooth weighted round robin sequences');

test('it keeps the current weights summing to zero after every selection', function () {
    $algorithm = new SmoothWeightedRoundRobin;
    $weights = [1 => 3, 2 => 2, 3 => 1];
    $state = [];

    foreach (range(1, 20) as $ignored) {
        $state = $algorithm->advance($weights, $state)['currentWeights'];

        expect(array_sum($state))->toBe(0);
    }
});

test('it never runs a destination longer than its weight', function () {
    [$sequence] = rotate([1 => 3, 2 => 1, 3 => 1], 30);

    $longestRun = 1;
    $run = 1;

    foreach (array_slice($sequence, 1) as $index => $id) {
        $run = $id === $sequence[$index] ? $run + 1 : 1;
        $longestRun = max($longestRun, $run);
    }

    expect($longestRun)->toBeLessThanOrEqual(3);
});

test('it restarts the cycle when the stored state does not sum to zero', function () {
    $algorithm = new SmoothWeightedRoundRobin;

    $result = $algorithm->advance([1 => 3, 2 => 1, 3 => 1], [1 => 99, 2 => 99, 3 => 99]);

    expect($result['destinationId'])->toBe(1)
        ->and($result['currentWeights'])->toBe([1 => -2, 2 => 1, 3 => 1]);
});

test('it ignores stored entries for destinations that no longer exist', function () {
    $algorithm = new SmoothWeightedRoundRobin;

    $result = $algorithm->advance([1 => 1, 2 => 1], [1 => 0, 2 => 0, 99 => 500]);

    expect($result['currentWeights'])->toHaveKeys([1, 2])
        ->and($result['currentWeights'])->not->toHaveKey(99);
});

test('it returns no destination for an empty weight set', function () {
    $result = (new SmoothWeightedRoundRobin)->advance([], []);

    expect($result['destinationId'])->toBeNull();
});

test('it breaks ties by the lowest destination id', function () {
    $result = (new SmoothWeightedRoundRobin)->advance([5 => 1, 9 => 1], []);

    expect($result['destinationId'])->toBe(5);
});
