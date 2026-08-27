---
paths:
  - 'app/Support/UserAgent/**'
---

# User Agent

## Scripted user agents count as desktop traffic, by decision
matomo/device-detector does not flag `curl`, `wget` or `python-requests` as bots — `isBot()` is false — so those hits are classified `desktop` and pass the `excludingBots()` stats filter. Real crawlers (Googlebot, bingbot) are caught correctly.

Settled 2026-08-27: accepted as is, no explicit user-agent rule. This is a library behaviour, not a bug in `DeviceTypeResolver`, and a version bump will not change it. If scripted traffic ever needs excluding it takes a deliberate pattern rule plus new tests.
