---
paths:
  - 'app/Support/Stats/**'
---

# Stats

## Group chart buckets by the alias, never by the expression
The bucket expression carries `?` bindings for the timezone offset. Repeating it in GROUP BY fails on MySQL with ONLY_FULL_GROUP_BY (error 1055): the checker compares parsed expressions, and two placeholders are two distinct parameter markers even when the SQL text is byte identical. Use `->groupBy('bucket')` — the select alias — which MySQL and SQLite both resolve.

The suite cannot catch this: `phpunit.xml` pins SQLite, which has no ONLY_FULL_GROUP_BY. It went green while every chart request 500'd against the real database. Verify any new grouped analytics query against MySQL before calling it done.

## Bucket keys must be identical on MySQL and SQLite
`BucketExpression` has a MySQL arm and a SQLite arm, and both must agree with `Granularity::keyFor()` byte for byte — production runs MySQL, the suite runs SQLite.

Two traps, both verified empirically:
- Never bucket weeks by week number. `strftime('%W')` gives `2026-34` and `DATE_FORMAT('%u')` gives `2026-35` for the same week. Anchor to the Monday date instead (`2026-08-24`), which both produce. Hour, day and month formats already agree.
- Never use `CONVERT_TZ`: it returns NULL unless MySQL's timezone tables are loaded, and they are not. Apply the offset as a minute interval (`DATE_ADD(created_at, INTERVAL ? MINUTE)` / `datetime(created_at, ?)`).

The offset travels as a binding, not interpolated, so `sql()` stays a `literal-string` and satisfies `selectRaw()` under PHPStan level 7. `bindings()` counts the `?` in the SQL rather than tracking a number separately, because the MySQL week arm names the shifted timestamp twice.

## Every indicator is a relative change, CTR included
`Indicator` reports relative change for every metric, so click-through-rate moving 15.0% → 15.5% reads `up 3.3`, not the 0.5 percentage-point delta.

Settled 2026-08-27: consistency of the `{value, previous_value, indicator}` envelope beats per-metric special casing, even though the original design sketch's `0.42` reads like points. Do not special-case CTR.

The rate is always a magnitude — direction lives in `position`. No baseline gives `flat` with a **null** rate, which is how a caller distinguishes "did not move" from "nothing to compare against". Keep that null; zero would collapse the two.

## Stats and chart are two endpoints with two default ranges
`DestinationStatsBuilder::stats()` returns `range` + `kpis`; `chart()` returns `range` + `series` + `tiles`. They are split because they are read on different windows: `DestinationStatsRequest` defaults to `all_time`, `DestinationChartRequest` to `last_30_days`. Both extend `DestinationReportRequest`, which owns the shared `range` rule and the `view` gate.

Settled 2026-08-28: the tiles (click-through rate, avg daily clicks, top country/device) stay on `/chart` even though they are aggregates, not series — that was the user's call, so do not "tidy" them over to `/stats`.

Both reports still run `totals()`, so neither is free; do not merge them back to save a query.

## Plan and customer attribution is stamped on the click, not joined
`traffic_rotator_clicks.plan_uid` / `customer_uid` are copied off the destination by `RecordRotatorClick` when the row is written. The breakdowns group the click table alone; nothing joins back to `traffic_rotator_destinations`. The columns there are the *current* attribution and only seed new clicks.

So the figures answer "what did this plan earn", not "what does it own today" — moving a destination onto another plan leaves its past traffic where it was earned, and a plan no destination carries any more still reports its history. The flip side is that a *correction* does not propagate either: a typo'd `plan_uid` stays in the history until someone rewrites those rows by hand. That was the accepted trade (2026-08-30, the user's call after the join version shipped) because these figures are expected to drive payouts, where retroactive re-attribution is a correctness bug.

Stamping is free on the write path: `RecordRotatorClick` already did a primary key lookup on the destination to confirm it still exists, so that same lookup now returns the two values instead of a boolean. Do not move this into `DestinationCandidate`/`RotatorSnapshot` — the cached snapshot would go stale and the shape change buys nothing.

`TrafficRotatorClickFactory::forDestination()` copies the identifiers the same way the job does. A test that needs history the destination has since moved away from uses `attributedTo()`.

Index each breakdown rides is `(rotator_id, uid, device_type)`. The third column is not optional: the bot filter reads `device_type`, and without it in the index every grouped row is fetched from the table. Verified on MySQL 8 — the grouping is `Using index` with no temp table; the residual `Using temporary; Using filesort` is only the `ORDER BY clicks DESC`, which sorts distinct plans, not clicks.

Rows with a null identifier are dropped rather than grouped into a null bucket: clicks on destinations nobody had attributed and fallback hits (`destination_id` null) would both land there, and one row cannot mean both. The breakdowns therefore do not add up to the rotator's `total_clicks`.
