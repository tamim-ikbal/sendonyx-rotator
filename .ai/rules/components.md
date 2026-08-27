---
paths:
  - resources/js/components/api-endpoint-card.tsx
---

# Components

## The API console omits the session cookie and never prints the token
`fetch` in `ApiEndpointCard` passes `credentials: 'omit'` on purpose. With the cookie attached a 200 would only prove you are logged into the dashboard in that tab; without it, the response proves the pasted Sanctum token itself works, which is the point of the console.

The token lives in `pages/docs/index.tsx` component state only — never localStorage, never the curl snippet, which always shows `$ROTATOR_API_TOKEN` (`TOKEN_PLACEHOLDER`). A curl command is the thing most likely to be pasted into a ticket, and a Sanctum token cannot be un-pasted.

Snippet and live request are both built by `lib/api-request.ts`, so they cannot describe different calls. Keep any new field going through `buildPath`/`buildBody`.
