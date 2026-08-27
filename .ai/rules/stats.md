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
