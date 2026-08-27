---
paths:
  - routes/web.php
---

# Routes

## `/` is the traffic rotator, not a landing page
`GET /` is `rotator.redirect` and 302s to an affiliate destination. The marketing page lives at `/welcome` and keeps the `home` route name.

Never send an application user to `/`. Any `redirect('/')` drops them onto someone's affiliate offer. Use `to_route('home')`.

Already fixed in three places: `ProfileController::destroy`, Fortify's logout (`config/fortify.php` → `redirects.logout`), and `VerificationNotificationTest` (which relied on `back()` falling through to `/`).

## Dashboard rotator routes are `rotator.*`, the API owns `rotators.*`
`routes/api.php` already registers `rotators.index|show` (plus `rotators.destinations.*`). The Inertia dashboard in `routes/web.php` therefore uses the singular `rotator.` prefix — `rotator.index`, `rotator.create`, `rotator.store`, `rotator.show`, `rotator.edit`, `rotator.update` — alongside the existing `rotator.redirect`.

Reusing the plural would collide. Duplicate route names do not fail at boot: they only surface as an exception at `route:cache` time, i.e. in deploy rather than in the suite. Keep the two prefixes apart.

`rotators/create` must stay registered before `rotators/{rotator}`; the parameter binds on uuid and would otherwise swallow it.
