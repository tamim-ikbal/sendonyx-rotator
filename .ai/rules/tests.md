---
paths:
  - 'tests/**'
---

# Tests

## Redis tests must pin every connection to database 15
`TestCase::skipUnlessRedisIsAvailable()` checks `default` AND `cache`, because they are separate Redis databases (`REDIS_DB` / `REDIS_CACHE_DB`). Both are pinned to 15 in `phpunit.xml`.

Guarding only `default` is not enough: a test calling `Redis::connection('cache')->flushdb()` would wipe the developer's real cache database. Add any new connection to that guard before a test touches it.

Related: never run `migrate:fresh --env=testing` — there is no `.env.testing`, so it resolves to `.env` and drops the dev MySQL database.
