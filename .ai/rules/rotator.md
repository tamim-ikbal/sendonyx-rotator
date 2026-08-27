---
paths:
  - 'app/Http/Controllers/Rotator/**'
---

# Rotator

## The redirect deliberately ignores rotator status
`RedirectController` does not check `TrafficRotator::$status`. A PAUSED rotator keeps rotating; only `DestinationStatus` affects which destinations are candidates.

Settled 2026-08-27 after the alternatives (fall through to `default_destination_url`, or 404) were put to the user and declined. Rotator status is decorative for now — a mis-click in the dashboard must never take the whole network offline. Do not "fix" this as an oversight; changing it is a product decision.
