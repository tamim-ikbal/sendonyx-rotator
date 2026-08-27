---
paths:
  - app/Models/User.php
  - 'app/Models/TrafficRotator*.php'
---

# Models

## User role is deliberately not mass assignable
`role` (App\Enums\UserRole, cast on User) is intentionally left out of the `#[Fillable]` attribute so a registration/profile request can never escalate itself to `admin`/`super_admin`.

Set it explicitly instead: `$user->role = UserRole::ADMIN; $user->save();` or `forceFill([...])`. Model factories bypass guarding, so `User::factory()->role(UserRole::ADMIN)` works as-is.

## Dashboard metric labels do not map onto the schema
The agreed meanings, because the labels are not self-explanatory:

- Clicks Received — clicks on this destination in range.
- Unique Visitors — `COUNT(DISTINCT visitor_id)` on this destination in range.
- Traffic Received — clicks on the *whole rotator* in range, limited to `created_at >= destination.created_at`. The pool this destination competed for, not its own clicks.
- Click Through Rate — Clicks Received ÷ Traffic Received. This is what reveals weight under-delivery.
- Avg Daily Clicks — clicks ÷ days in range.
- Active Since — `destination.created_at` plus a day count.

Rotator and destination models declare `$attributes` defaults mirroring the column defaults. Without them a freshly created model serialises `status` as null until it is reloaded, because a database default only lands on read.
