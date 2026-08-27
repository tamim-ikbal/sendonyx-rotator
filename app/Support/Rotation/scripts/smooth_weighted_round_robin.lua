-- Smooth weighted round robin, mirroring App\Support\Rotation\SmoothWeightedRoundRobin.
--
-- KEYS[1]     state hash: destination id -> current weight
-- KEYS[2]     index set holding every state key used by this rotator
-- ARGV[1]     the unprefixed state key, recorded in the index set so the keys
--             can be deleted later without SCAN and without the client prefix
--             being applied twice
-- ARGV[2]     ttl in seconds
-- ARGV[3..n]  destination id, weight, destination id, weight, ...
--             ordered by destination id ascending; ties resolve to the first
--             entry, so that ordering is part of the behavioural contract.
--
-- The whole read, decide and write cycle runs inside one EVAL, and Redis runs
-- scripts to completion, so concurrent requests cannot lose an update.
--
-- Returns the winning destination id, or nil when no destinations were given.

local key = KEYS[1]
local index = KEYS[2]
local rawKey = ARGV[1]
local ttl = tonumber(ARGV[2])
local count = (#ARGV - 2) / 2

if count < 1 then
    return nil
end

local ids = {}
local weights = {}
local total = 0

for i = 1, count do
    ids[i] = ARGV[2 * i + 1]
    weights[i] = tonumber(ARGV[2 * i + 2])
    total = total + weights[i]
end

-- Read by explicit field name so a stale field left by an older fingerprint can
-- never be picked up. Missing fields come back as false, which becomes zero.
local stored = redis.call('HMGET', key, unpack(ids))
local current = {}
local sum = 0

for i = 1, count do
    local value = tonumber(stored[i])

    if value == nil then
        value = 0
    end

    current[i] = value
    sum = sum + value
end

-- The current weights always sum to exactly zero: each pass adds the total
-- across all destinations and subtracts the total from one. Any other sum means
-- the state was truncated or tampered with, so restart the cycle.
if sum ~= 0 then
    for i = 1, count do
        current[i] = 0
    end
end

local winner = 1

for i = 1, count do
    current[i] = current[i] + weights[i]

    if current[i] > current[winner] then
        winner = i
    end
end

current[winner] = current[winner] - total

local write = {}

for i = 1, count do
    write[#write + 1] = ids[i]
    write[#write + 1] = tostring(current[i])
end

redis.call('HSET', key, unpack(write))
redis.call('EXPIRE', key, ttl)

redis.call('SADD', index, rawKey)
redis.call('EXPIRE', index, ttl)

return ids[winner]
