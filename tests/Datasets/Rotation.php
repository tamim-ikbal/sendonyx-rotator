<?php

/*
 * Golden smooth weighted round robin sequences.
 *
 * Every rotation state store must reproduce these exactly. The pure PHP
 * implementation and the Redis Lua script are both asserted against this one
 * dataset, so a divergence between them shows up as a single failing case
 * rather than as silently skewed traffic in production.
 *
 * Keys are destination ids, ordered ascending, which is also the tie break
 * order: when current weights tie, the lowest destination id wins.
 */
dataset('smooth weighted round robin sequences', [
    'weight 3/1/1 over one cycle' => [[1 => 3, 2 => 1, 3 => 1], 5, [1, 2, 1, 3, 1]],
    'weight 3/1/1 over two cycles' => [[1 => 3, 2 => 1, 3 => 1], 10, [1, 2, 1, 3, 1, 1, 2, 1, 3, 1]],
    'weight 2/1' => [[1 => 2, 2 => 1], 3, [1, 2, 1]],
    'equal weights rotate evenly' => [[1 => 1, 2 => 1, 3 => 1], 6, [1, 2, 3, 1, 2, 3]],
    'the heavier destination may lead' => [[1 => 1, 2 => 3], 4, [2, 1, 2, 2]],
    'equal heavy weights alternate' => [[1 => 3, 2 => 3], 6, [1, 2, 1, 2, 1, 2]],
    'weight 3/2/1 interleaves' => [[1 => 3, 2 => 2, 3 => 1], 6, [1, 2, 1, 3, 2, 1]],
]);
