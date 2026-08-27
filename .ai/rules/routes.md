---
paths:
  - routes/web.php
---

# Routes

## `/` is the traffic rotator, not a landing page
`GET /` is `rotator.redirect` and 302s to an affiliate destination. The marketing page lives at `/welcome` and keeps the `home` route name.

Never send an application user to `/`. Any `redirect('/')` drops them onto someone's affiliate offer. Use `to_route('home')`.

Already fixed in three places: `ProfileController::destroy`, Fortify's logout (`config/fortify.php` → `redirects.logout`), and `VerificationNotificationTest` (which relied on `back()` falling through to `/`).
