# TrafficRotator — build handoff

**Status as of 2026-08-27.** Phases 0–6 complete and verified. **The build is done.**

Approved plan (full detail): `/Users/tamim/.claude/plans/memoized-jumping-treehouse.md`

---

## Current state: green

```bash
php artisan test --compact          # 210 tests, 207 passed, 3 skipped, 0 failed
vendor/bin/phpstan analyse          # 0 errors (level 7)
vendor/bin/pint --format agent app/ database/ config/ tests/ routes/
yarn run types:check                # tsc clean; lint:check and format:check clean too
yarn run build                      # required after adding a page component (see below)
```

Phase 3 was verified end to end against Herd on the real stack (Redis cache, Redis rotation
state, Redis queue): ten hits produced `b a c a a b a c a a` — the golden 3/1/1 cycle offset by
leftover cursor state — and `queue:work` drained eleven jobs into eleven click rows.

Phases 4–5 were verified the same way, over real HTTP against `sendonyx-rotator.test` with a
real Sanctum token: index/show/store/update on both resources, 401 unauthenticated, 422 on a bad
weight and on a `javascript:` url, 404 on a mismatched rotator/destination pair, and all six
chart ranges at **exactly 3 queries each**. Smoke data was removed afterwards; the dev database
is back to 1 rotator / 3 destinations / 0 tokens.

**Do not pass `bootstrap/` to Pint.** It reformats the generated `bootstrap/cache/*.php`.

`--dirty` is unavailable: **this project is not a git repo.** There is no VCS safety net for
the remaining work. Consider `git init` before continuing.

---

## What this is

Backend for the **Onyx Traffic Network (OTN)**: a shared traffic rotator. `GET /` picks a member
destination by weighted round robin and redirects to it; every redirect is logged; a Sanctum
authenticated JSON API manages rotators/destinations and serves dashboard analytics.

**API only.** The admin dashboard consuming this API lives in a separate application. The one
piece of frontend in scope is a token settings page (Phase 6), because that is how the other
application obtains credentials.

---

## Settled decisions

| Area | Decision |
| --- | --- |
| Scale | ~10–100 req/s. Redis for WRR state, queued job for click writes. |
| Algorithm | nginx-style **smooth WRR**, atomic Redis Lua script. |
| Stickiness | **None.** Every hit advances the cursor; `visitor_id` is analytics only. |
| Home rotator | `TrafficRotator::first()`, cached. |
| Fallback | active destination → `rotator.default_destination_url` → `abort(404)`. |
| Fallback clicks | Logged with `destination_id` NULL so leaked traffic stays visible. |
| Redirect | `302` + `Cache-Control: no-store` (a cacheable redirect breaks rotation). |
| `visitor_id` | `rotator_vid` cookie (32 hex, 1 yr); HMAC(ip+ua+date) fallback when blocked. |
| IP | Stored only as HMAC (`ip_hash`). Raw IP never persisted. |
| Country | **Deferred.** Column nullable, nothing populates it. |
| Device | `device_type` column, parsed in the job by `matomo/device-detector`. |
| Bots | Logged and rotated, **excluded from stats** via a `WHERE` clause. |
| Auth | **Sanctum tokens** on `routes/api.php`. `HasApiTokens` already added to `User`. |
| Ownership | `traffic_rotators.user_id` is the boundary; destinations inherit via policy. |
| Weight | Integer **1–3** (the ⚡⚡⚡ priority tiers in the design). |
| Status | `ACTIVE` / `PAUSED`. Paused skipped by rotation, history kept. |
| Enum case style | **SCREAMING_SNAKE** (user's explicit choice; matches `UserRole`). |
| WRR invalidation | Observer on save/delete **plus** a weight/membership fingerprint in the key. |
| Queue | `QUEUE_CONNECTION=redis`. Applied to `.env`; `.env.example` deliberately stays on `database` so a bare CI runner can still `composer setup`. |
| Timezone | `config('app.timezone')`; clicks stored UTC. |
| Indicators | vs the immediately preceding equal-length period. |
| Chart buckets | Adaptive hour → day → week → month. Zero-filled. |

### Metric definitions (dashboard labels do not map 1:1 onto the schema)

- **Clicks Received** — clicks on this destination in range.
- **Unique Visitors** — `COUNT(DISTINCT visitor_id)` on this destination in range.
- **Traffic Received** — clicks on the *whole rotator* in range, limited to
  `created_at >= destination.created_at`. The pool this destination competed for.
- **Active Since** — `destination.created_at` plus a day count.
- **Click Through Rate** — Clicks Received ÷ Traffic Received. Reveals weight under-delivery.
- **Avg Daily Clicks** — clicks ÷ days in range.
- **Top Country / Top Device** — `{name, visitor_rate}`. Country stays null until geo lands.

### Out of scope

Network Stats panel, Recent Promotional Emails, Plan Usage & Summary, Your Rotator Spot, country
detection, destination `index` endpoint, any `DELETE` endpoint, the rotator dashboard UI.

---

## Hard-won facts — verified empirically, do not re-derive

1. **CI runs no service containers.** `.github/workflows/tests.yml` is a bare ubuntu runner.
   `phpunit.xml` pins `sqlite :memory:`, `array` cache, `sync` queue. **The suite must pass with
   neither Redis nor MySQL.** This is why `RotationStateStore` has two drivers.

2. **SQLite and MySQL disagree on week numbers.** `strftime('%W')` → `2026-34`,
   `DATE_FORMAT('%u')` → `2026-35`. Anchoring weeks to the **Monday date** gives `2026-08-24` on
   both. **Never bucket weeks by week number.** Hour/day/month formats agree exactly.

3. **`CONVERT_TZ` returns NULL on this MySQL** — timezone tables are not loaded. Apply the
   timezone as a **minute-interval offset** instead (`DATE_ADD(created_at, INTERVAL ? MINUTE)` /
   `datetime(created_at, '+N minutes')`). `config('app.timezone')` is `UTC`, so the offset is 0
   and the shift compiles away.

4. **phpredis applies the key prefix to `KEYS[n]` but not `ARGV[n]`.** State keys must travel in
   KEYS; destination ids must travel in ARGV.

5. **Laravel's `Connection::scan()` returns `[$cursor, $keys]`**, not bare keys, and scanned keys
   come back prefixed while `del` re-prefixes them. Avoided entirely: the Lua maintains an index
   set of unprefixed key names, so `forget()` is `SMEMBERS` + `DEL`.

6. **`Illuminate\Contracts\Cache\Repository` has no `lock()`.** Reach it via
   `getStore() instanceof LockProvider`.

7. **Seeders run inside `Model::unguarded()`**, which is why the seeder can set `user_id` even
   though it is deliberately not in `#[Fillable]`. Mass-assignment protection **is** enforced for
   application code — verified.

8. **MySQL's identifier limit is 64 chars.** Auto-generated composite index names on
   `traffic_rotator_clicks` exceed it, so index names are set explicitly (`trc_` / `trd_` / `tr_`).

9. **Don't run `migrate:fresh --env=testing`.** There is no `.env.testing`, so it resolves to
   `.env` (MySQL) and drops the dev database. Tests already use in-memory SQLite via `phpunit.xml`
   and need no manual migration. *(This bit me: it dropped `sendonyx_rotator_db` mid-build. Only
   the seeded `admin@sendonyx.com` and starter-kit tables were present, so loss was minimal.)*

10. **`config/cache.php` sets `serializable_classes => false`.** Laravel will not unserialize *any*
    class out of a cache store. A cached object comes back as `__PHP_Incomplete_Class` and throws
    a `TypeError`. **The suite cannot catch this** — `phpunit.xml` pins the array store, which
    keeps live objects and never serializes. It went green while every request after the first
    500'd in the browser. Cache plain arrays; `RotatorSnapshot`/`DestinationCandidate` cross the
    cache via `toArray()`/`fromArray()`, pinned by a round-trip test in `RotatorCacheTest`.

11. **`REDIS_DB` and `REDIS_CACHE_DB` are different databases.** The old guard only checked the
    `default` connection, so a test hitting `Redis::connection('cache')->flushdb()` wiped the dev
    cache. `skipUnlessRedisIsAvailable()` now checks both, and `phpunit.xml` pins both to 15.

12. **`vendor/bin/pint bootstrap/` reformats `bootstrap/cache/*.php`.** Those are generated. Run
    `php artisan package:discover` if it happens; do not pass `bootstrap/` to Pint.

13. **MySQL's `ONLY_FULL_GROUP_BY` rejects a repeated bucket expression.** The expression carries
    `?` bindings for the timezone offset, and MySQL compares *parsed* expressions: two
    placeholders are two distinct parameter markers even when the SQL text is byte identical, so
    `GROUP BY <expr>` fails with error 1055 against `SELECT <expr> AS bucket`. Group by the alias
    (`->groupBy('bucket')`), which MySQL and SQLite both resolve. **The suite cannot catch this** —
    SQLite has no `ONLY_FULL_GROUP_BY`. It was green while every chart request 500'd on MySQL.

14. **PHPStan requires `selectRaw()` to receive a `literal-string`.** Interpolating even an
    integer breaks it (`'INTERVAL '.$offset` is `non-falsy-string`, and `sprintf('%d')` is too).
    That is why the timezone offset travels as a binding rather than inlined, and why
    `BucketExpression::bindings()` derives its count with `substr_count($sql, '?')` — the MySQL
    week arm names the shifted timestamp twice, and a separately tracked number would drift.

15. **A database column default does not reach a freshly created model.** `TrafficRotatorResource`
    read `$this->status->value` on a just-`save()`d model and fatalled on null. Both models now
    declare `$attributes` defaults mirroring the migration, so the created response matches the
    stored row without a reload.

16. **Sanctum's guard is `['web']`, so a session authenticates the API too.** A test that calls
    `actingAs()` and then sends a bearer token is really authenticating by session, which hides
    whether the token still works. `tests/Feature/Settings/ApiTokenTest.php` calls
    `flushSession()` + `Auth::forgetGuards()` between the two.

17. **A new Inertia page needs `yarn run build` before its feature test passes.** Blade resolves
    the page component through the Vite manifest, so a missing entry is a 500 that
    `assertInertia` reports only as *"Not a valid Inertia response."*

18. **`php artisan wayfinder:generate` needs `--with-form`.** `vite.config.ts` sets
    `formVariants: true`, but the bare CLI command does not, and running it silently strips
    `.form()` off every generated action — which every existing page uses.

19. **matomo does not classify `curl/8.x` as a bot** — `isBot()` is false, so scripted traffic is
    logged as `desktop` and *counts towards stats*. Real crawlers (Googlebot, bingbot) are caught.
    If this matters, it needs an explicit rule, not a library upgrade.

### Golden WRR sequences (the behavioural contract)

Both the PHP and Lua implementations are asserted against these. Destination ids ascending;
ties resolve to the lowest id.

| Weights | Picks | Sequence |
| --- | --- | --- |
| 3/1/1 | 5 | `1 2 1 3 1` |
| 3/1/1 | 10 | `1 2 1 3 1 1 2 1 3 1` |
| 2/1 | 3 | `1 2 1` |
| 1/1/1 | 6 | `1 2 3 1 2 3` |
| 1/3 | 4 | `2 1 2 2` |
| 3/3 | 6 | `1 2 1 2 1 2` |
| 3/2/1 | 6 | `1 2 1 3 2 1` |

Invariant: current weights always sum to exactly zero. Any other sum means corrupted state and
the cycle restarts.

---

## Done

### Phase 0 — repo unblocked

CI was **already red** before this work started. Now 0 PHPStan errors.

- `UserRole` SCREAMING_SNAKE rename propagated: `app/Models/User.php`,
  `database/factories/UserFactory.php`, `database/migrations/0001_01_01_000000_create_users_table.php`,
  `tests/Feature/UserRoleTest.php`, `tests/Feature/SeedSuperAdminUserTest.php`, `.ai/rules/models.md`.
- `UserFactory::withTwoFactor()` had an empty body on a non-nullable return type. **Implemented**
  rather than removed (the plan said remove) because `tests/Feature/Auth/AuthenticationTest.php:33`
  references it. That test is skipped — Fortify 2FA is disabled and the `two_factor_*` columns were
  never migrated. Pre-existing gap, left alone.
- `config/sanctum.php` — `explode()` on `env()`'s `bool|string`. Narrowed with a typed default.

### Phase 1 — schema

- `composer require matomo/device-detector` (^6.5) — the one approved new dependency.
- `app/Enums/`: `RotatorStatus`, `DestinationStatus`, `DeviceType`, `IndicatorPosition`,
  `Granularity` (carries `keyFor`/`startOf`/`advance`), `StatsRange` (carries
  `granularity()`/`hasBaseline()`).
- `config/rotator.php`.
- Migrations `2026_08_27_10000{0,1,2}_*` — verified on **both** SQLite and MySQL.
- `app/Models/TrafficRotator{,Destination,Click}.php` — `#[Fillable]` attributes (not `$fillable`),
  `HasUuids` with `uniqueIds() => ['uuid']` so the integer PK stays auto-incrementing,
  `getRouteKeyName() => 'uuid'`.
- `TrafficRotator::activeDestinations()` has an explicit `orderBy('id')` — **this ordering is part
  of the rotation contract**, since ties break on the first candidate.
- `TrafficRotatorClick::excludingBots()` scope. Written as an explicit
  `whereNull OR != 'bot'` group: a plain `!=` silently drops NULL rows under SQL three-valued
  logic, which would hide real traffic whenever classification lags.
- Factories for all three + `TrafficRotatorSeeder` (wired into `DatabaseSeeder`).
- `User`: `HasApiTokens` + `rotators()` relation.
- `tests/Feature/Rotator/TrafficRotatorModelTest.php` (5 tests).

### Phase 2 — rotation engine

- `app/Support/Rotation/SmoothWeightedRoundRobin.php` — pure, I/O free, **the normative spec**.
- `app/Support/Rotation/scripts/smooth_weighted_round_robin.lua` — mirrors it, atomic, also
  maintains the index set used by `forget()`.
- `RotationStateStore` contract + `RedisRotationStateStore` (production, `command('eval', …)`)
  and `CacheRotationStateStore` (portable, used by tests/CI).
- Bound in `AppServiceProvider::registerRotationStateStore()` off `config('rotator.state_store')`.
- `phpunit.xml`: `ROTATOR_STATE_STORE=array`, `ROTATOR_CACHE_STORE=array`, `REDIS_DB=15`.
- `tests/TestCase.php`: `skipUnlessRedisIsAvailable()` — also refuses to run unless `REDIS_DB=15`,
  so a test run can never flush a real database.
- `tests/Datasets/Rotation.php` — the golden dataset, shared by both drivers.
- `tests/Unit/Rotation/SmoothWeightedRoundRobinTest.php` (27), 
  `tests/Feature/Rotation/RotationStateStoreTest.php` (20).

**Honest limitation:** the cache driver is not atomic across processes, so CI proves the
*algorithm* but not the *concurrency guarantee*. The Redis-gated tests cover the rest and skip
cleanly when Redis is absent. A `pcntl_fork` concurrency test was designed but not written.

### Phase 3 — redirect + click logging

`GET /` is now `rotator.redirect`; the marketing page moved to `/welcome` and **kept the `home`
route name**, so the auth layouts and `tsc` are untouched.

- `app/Support/Rotation/`: `DestinationCandidate`, `RotatorSnapshot` (both with
  `toArray`/`fromArray`), `RotationDecision`, `RotatorCache`, `DestinationResolver`,
  `VisitorIdentity`.
- `app/Support/UserAgent/DeviceTypeResolver.php` — bot / mobile / tablet / desktop, matched on
  matomo's device type constants rather than name strings.
- `app/Observers/TrafficRotator{,Destination}Observer.php` via `#[ObservedBy]`. Both flush the
  snapshot **then** the WRR cursor.
- `app/Jobs/RecordRotatorClick.php` — scalars only, no `SerializesModels`. Confirms both parents
  still exist before inserting, and truncates the referrer to 2048.
- `app/Http/Controllers/Rotator/RedirectController.php`.
- `AppServiceProvider` gained `registerRotatorCache()` and `registerVisitorIdentity()`. The HMAC
  secret is `Encrypter::getKey()`, so the `base64:` prefix is decoded exactly once.
- `rotator_vid` added to `encryptCookies(except:)`; `VisitorIdentity` therefore validates the
  incoming cookie is 32 hex characters before trusting it.
- Tests: `Rotator/RedirectTest.php` (18), `Rotator/RotatorCacheTest.php` (4),
  `Jobs/RecordRotatorClickTest.php` (4), `Unit/UserAgent/DeviceTypeResolverTest.php` (7).

**Three redirects to `/` were fixed, not one.** The plan flagged `ProfileController::destroy`;
`Fortify`'s logout response and `VerificationNotificationTest` had the same bug. Logout is now
pinned via `config('fortify.redirects.logout')`.

**Deliberately not done:** the redirect ignores `TrafficRotator::$status`. The plan says the home
rotator is `TrafficRotator::first()`, full stop, and inventing a paused-rotator fallback could
silently kill live traffic. If a paused rotator should stop rotating, that is a decision to make
explicitly — see *Open questions*.

**Seeder caveat:** `DatabaseSeeder` uses `WithoutModelEvents`, so re-seeding does **not** flush the
snapshot cache. Run `php artisan cache:clear` after seeding.

---

## Done, continued

### Phase 4 — API

`routes/api.php`, all behind `auth:sanctum`:

```
GET|POST      /api/rotators                                           rotators.index|store
GET|PUT|PATCH /api/rotators/{rotator}                                 rotators.show|update
POST          /api/rotators/{rotator}/destinations                    rotators.destinations.store
GET|PUT|PATCH /api/rotators/{rotator}/destinations/{destination}      rotators.destinations.show|update
GET           /api/rotators/{rotator}/destinations/{destination}/chart rotators.destinations.chart
```

No destination `index`, no `DELETE` — as decided. The rotator `show` response therefore carries
its destinations, since that is the only way to enumerate them.

- `app/Policies/TrafficRotatorPolicy.php` — `view` / `update`, returning `Response::allow()` or
  `Response::denyAsNotFound()`. Attached with `#[UsePolicy]` on the model. Destinations have no
  policy: they inherit through the parent. **Write requests authorise in the Form Request**, so the
  404 lands before validation could leak the same existence a 403 would.
- `app/Concerns/RotatorValidationRules.php` (rule shapes, no presence rules — `required` versus
  `sometimes` stays at the call site) and `app/Concerns/ResolvesRotatorRoute.php` (typed accessors
  for the bound models).
- `app/Http/Requests/TrafficRotator/` — `StoreRotatorRequest` (derives the slug from the name when
  omitted, and reports a collision rather than silently suffixing it), `UpdateRotatorRequest`,
  `StoreDestinationRequest`, `UpdateDestinationRequest`, `DestinationChartRequest`.
  **Updates are partial** (`sometimes`); the routes answer `PUT` and `PATCH` alike.
- `url` is `url:http,https`, not bare `url` — every one of these values ends up in a `Location`
  header, and bare `url` accepts `javascript:` and `data:`.
- `app/Http/Resources/TrafficRotator{,Destination}Resource.php`. The integer primary key and
  `user_id` never leave the application.
- `Route::scopeBindings()` wraps the nested resource, so a mismatched pair is a router 404.
- Tests: `Api/TrafficRotatorApiTest.php` (17), `Api/TrafficRotatorDestinationApiTest.php` (17),
  `Policies/TrafficRotatorPolicyTest.php` (8).

### Phase 5 — stats / chart

**3 queries per request, at every range** — confirmed by query log against MySQL.

- `app/Support/Stats/StatsDateRange.php` — the window, entirely in the display timezone.
  `windowStart = max(previousStart, destination.created_at)`, computed in PHP.
  `all_time` collapses the baseline onto the start, so the doubled range is never scanned.
- `app/Support/Stats/BucketExpression.php` — the MySQL and SQLite arms. See hard facts 13–14.
- `app/Support/Stats/Indicator.php` — the rate is a **magnitude**; direction lives in `position`,
  derived by comparing the values. No baseline → `flat` with a **null** rate, which is how a caller
  tells "did not move" from "nothing to compare with".
- `app/Support/Stats/DestinationTotals.php`, `DestinationStatsBuilder.php`.
- `app/Http/Controllers/Api/DestinationChartController.php`.

Response is `{"data": {destination, range, kpis, series, tiles}}`. Deviations from the original
sketch: typos fixed (`indecator`, `top_cuntry`), `top_device` un-nested, a uniform
`{value, previous_value, indicator}` envelope, and `kpis` split from `tiles`.

**Note on the wire format:** a float with a zero fraction serialises as an integer (`75.0` → `75`);
PHP only keeps the `.0` with `JSON_PRESERVE_ZERO_FRACTION`. Normal for JSON, but pick fixture
numbers with a real fraction when asserting one.

- Tests: `Api/DestinationChartTest.php` (20), `Unit/Stats/StatsDateRangeTest.php` (9),
  `Unit/Stats/IndicatorTest.php` (8).

**The MySQL arm of `BucketExpression` cannot be covered by the suite.** It was verified instead by
running every granularity against the live MySQL server and SQLite side by side, at offset 0 and
+360 minutes, over four instants each: **32/32 matched `Granularity::keyFor()` exactly**, including
the year-boundary week (`2026-01-01` → `2025-12-29`). Re-run that comparison if either arm changes.

### Phase 6 — token settings page

- `app/Http/Controllers/Settings/ApiTokenController.php` (`edit` / `store` / `destroy`) and
  `app/Http/Requests/Settings/ApiTokenStoreRequest.php`. Routes named `api-tokens.*` in
  `routes/settings.php`, inside the existing `['auth', 'verified']` group.
- The plain-text token is session-flashed by `store()` and read as a prop on the redirect, so it is
  shown exactly once. Revocation looks the token up through `$request->user()->tokens()`, making
  another account's id a 404.
- `resources/js/pages/settings/tokens.tsx`, plus `resources/js/components/ui/table.tsx` (standard
  shadcn, no new npm dependency) and an "API tokens" entry in the settings sidebar.
  Uses the existing `useClipboard` hook — this is its first consumer — and the
  `Inertia::flash('toast', …)` + `sonner` convention.
- Tests: `Settings/ApiTokenTest.php` (8), including that a revoked token stops authenticating
  the API.

---

## Nothing is outstanding

Everything in the approved plan is built, the four open questions are answered (see below),
and the out-of-scope list above still stands.

## Conventions in force

- Models use `#[Fillable([...])]` / `#[Hidden([...])]` attributes, **not** `$fillable` arrays.
- Form Requests live in `app/Http/Requests/<Domain>/`; rule arrays extracted to traits in
  `app/Concerns/`.
- `CarbonImmutable` is the date class (`Date::use` in `AppServiceProvider`).
- Explicit return types and a docblock on every method. PHPStan level 7 over
  `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`.
- Pest 5. `RefreshDatabase` is bound to `Feature` only.
- Run `vendor/bin/pint --format agent <paths>` before finishing (`--dirty` needs git).

## Open questions — all resolved 2026-08-27

The user reviewed all four and chose to keep current behaviour in every case. **No code changed.**
Each is now recorded in `.ai/rules/` so it is not re-raised as an oversight.

1. **A `PAUSED` rotator keeps rotating.** The redirect deliberately ignores `TrafficRotator::$status`;
   only destination status gates candidates. Rotator status is decorative for now — a mis-click must
   not take the network offline. → `.ai/rules/rotator.md`
2. **Scripted traffic counts as desktop.** matomo does not flag `curl`/`wget`/`python-requests` as
   bots, so those hits pass the stats filter. Accepted; no explicit rule added. → `.ai/rules/user-agent.md`
3. **No admin override on ownership.** `user_id` is the only boundary; a `SUPER_ADMIN` gets 404 like
   anyone else, pinned by `TrafficRotatorPolicyTest`. → `.ai/rules/policies.md`
4. **CTR stays a relative change, not a percentage-point delta.** 15.0% → 15.5% reports `up 3.3`.
   Envelope consistency beats special casing one metric. → `.ai/rules/stats.md`

---

## Rules recorded via Boost `record-rule`

All in `.ai/rules/` and listed in `.ai/rules/index.md`:

- `rotation.md` — cache plain arrays never objects; the two-driver rationale, the tie-break
  contract, the phpredis KEYS-vs-ARGV prefix trap.
- `routes.md` — `/` is the rotator; never `redirect('/')`.
- `tests.md` — pin every Redis connection to database 15.
- `models.md` — user role is not mass assignable; the metric definitions; the `$attributes`
  defaults and why a column default is not enough.
- `policies.md` — cross-owner requests answer 404, authorisation happens in the Form Request,
  and there is no admin override on ownership.
- `stats.md` — group by the alias not the expression; bucket keys must match across drivers;
  every indicator is a relative change, CTR included.
- `rotator.md` — the redirect deliberately ignores rotator status.
- `user-agent.md` — scripted user agents count as desktop traffic, by decision.
