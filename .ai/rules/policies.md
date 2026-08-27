---
paths:
  - 'app/Policies/**'
---

# Policies

## Cross-owner rotator requests answer 404, not 403
`TrafficRotatorPolicy` returns `Response::denyAsNotFound()` rather than `deny()`. A 403 would confirm that the uuid the caller guessed resolves to a real rotator, and uuids are the only handle the API exposes.

It is the single authorisation point for the whole domain: destinations have no policy. A destination is owned by whoever owns its rotator, so reading one is `view` on the parent and writing one — creating included — is `update` on the parent.

Write requests authorise in the Form Request's `authorize()`, not in the controller, so the 404 lands before validation. Authorising after validation would let a 422 leak the same existence a 403 would.

## No admin override on rotator ownership
`TrafficRotatorPolicy` has no `before()` hook. `traffic_rotators.user_id` is the only boundary, so a SUPER_ADMIN gets the same 404 as anyone else on a rotator they do not own.

Settled 2026-08-27 — the user chose the hard boundary over a support-staff escape hatch. `TrafficRotatorPolicyTest` asserts it explicitly, so adding a `before()` hook means deleting a passing test: treat that test failing as the intended alarm, not as a stale assertion.

## One rotator per user, enforced by the `create` ability
`TrafficRotatorPolicy::create()` denies once the user owns any rotator — the product is deliberately limited to one rotator per user for now (asked for 2026-08-28). It is a `Response::deny()` with a message, not `denyAsNotFound()`: nothing about someone else's data is being hidden, so the caller gets a 403 explaining the limit.

Three places lean on it: `StoreRotatorRequest::authorize()` (via `Gate::inspect`, so the refusal lands before validation), `RotatorController::create()` (the form only exists to submit the write), and the `canCreateRotator` prop the rotators index uses to hide its New rotator button. Lifting the limit means changing the policy only — the other three follow.
