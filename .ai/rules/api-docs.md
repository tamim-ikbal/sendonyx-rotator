---
paths:
  - 'app/Support/ApiDocs/**'
---

# Api Docs

## ApiReference and routes/api.php must stay in step
`ApiDocsTest` asserts both directions: every documented endpoint resolves to a registered route, and every route under `api/` appears in `ApiReference`. Adding an API endpoint without documenting it fails the suite, which is deliberate — the docs page is the only reference the API has.

Enum options are read off the enums (`RotatorStatus`, `DestinationStatus`, `StatsRange`) rather than retyped, so a new case cannot leave the page describing the old set. Keep it that way.

`ApiEndpoint::$uri` is the router's own template, placeholders included, because that is what the drift test matches on. Do not "tidy" it into a display url.
