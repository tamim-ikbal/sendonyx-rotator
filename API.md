# Sendonyx Rotator — API Reference

A traffic rotator splits inbound visitors across a weighted set of affiliate
destinations. This API is what an external dashboard uses to read a rotator,
manage its destinations, and pull the reporting behind each one.

- **Base URL (production):** `https://your-domain.com`
- **Base URL (local, Laravel Herd):** `https://sendonyx-rotator.test`
- All API paths are prefixed with `/api`.
- All requests and responses are JSON. Send `Accept: application/json`.

---

## 1. Authentication

The API authenticates with **Laravel Sanctum personal access tokens**.

A user issues a token from the dashboard at `/settings/tokens`. The plaintext
token is shown **once**, immediately after creation — only a hash is stored, so
it can never be read back.

Send it as a bearer token on every request:

```
Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2
Accept: application/json
Content-Type: application/json
```

Tokens are created with the wildcard ability (`*`) — there is no per-scope
permission model. A missing or invalid token returns `401`.

There is **no rate limiting** on the API routes. Do not rely on that staying
true forever; handle `429` defensively.

### Ownership model

Everything is scoped to the token's owner:

- `GET /api/rotators` starts from the user's own rotators, so it can only ever
  return their rows.
- A rotator belonging to somebody else answers **`404`, not `403`** — deliberately.
  A `403` would confirm that a guessed UUID is real, and UUIDs are the only
  public handle the API exposes.
- Destinations have no ownership of their own. A destination is owned by whoever
  owns its rotator. Reading one is a `view` on the parent; creating or updating
  one is an `update` on the parent.
- Destinations are always addressed **through** their rotator, and the binding is
  scoped. A destination that belongs to a different rotator than the one in the
  URL is a `404` at the router, before any controller runs.

### Identifiers

Integer primary keys never leave the application. **Every resource is addressed
by its `uuid`**, and that is what every route binds on.

---

## 2. Resource shapes

### Rotator

```json
{
  "uuid": "9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118",
  "name": "Summer Offers",
  "slug": "summer-offers",
  "status": "active",
  "default_destination_url": "https://example.com/fallback",
  "destinations_count": 3,
  "destinations": [],
  "total_clicks": 4821,
  "unique_visitors": 3190,
  "created_at": "2026-05-12T09:14:02+00:00",
  "updated_at": "2026-08-21T16:40:55+00:00"
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `uuid` | string | Public handle. Use this in every URL. |
| `name` | string | Max 255 chars. |
| `slug` | string | Max 255, `alpha_dash`, unique across all rotators. |
| `status` | enum | `active` \| `paused` |
| `default_destination_url` | string \| null | `http`/`https` only, max 2048. |
| `destinations_count` | integer | **Only present on `index`.** |
| `destinations` | array | **Only present on `show`.** |
| `total_clicks` | integer | **Only present on `show`.** Lifetime clicks on the whole rotator, bots excluded. |
| `unique_visitors` | integer | **Only present on `show`.** Lifetime `COUNT(DISTINCT visitor_id)` on the whole rotator, bots excluded. |
| `created_at` / `updated_at` | ISO 8601 string | |

> **`destinations_count` and `destinations` are mutually exclusive.**
> The list endpoint counts them; the show endpoint embeds them. Neither key
> appears when it was not loaded — do not assume both exist.

> **`total_clicks` counts fallback hits too.** It is every click the rotator
> served, including the ones that went to `default_destination_url` and so have
> no destination behind them. The per-destination click counts will not add up
> to it whenever the fallback has fired.

> **Rotator `status` does not stop the rotation.** The public redirect ignores
> it entirely; only a destination's status affects which destinations are
> candidates. Treat rotator status as a dashboard label, not a kill switch.

### Destination

```json
{
  "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77",
  "url": "https://example.com/offer-a",
  "plan_uid": "plan_9f2a",
  "customer_uid": "cus_4b7e",
  "weight": 2,
  "status": "active",
  "created_at": "2026-06-02T11:03:47+00:00",
  "updated_at": "2026-08-19T08:22:10+00:00"
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `uuid` | string | Public handle. |
| `url` | string | `http`/`https` only, max 2048. Other schemes (`javascript:`, `data:`) are rejected — this value ends up in a `Location` header. |
| `plan_uid` | string \| null | The plan the destination was provisioned under. Max 255. |
| `customer_uid` | string \| null | The customer the destination belongs to. Max 255. |
| `weight` | integer | Priority tier, **1–3 only**. Traffic is split in proportion to weight. |
| `status` | enum | `active` \| `paused`. A paused destination takes no traffic. |

> **`plan_uid` and `customer_uid` are opaque to the rotator.** Nothing
> validates their shape, and they never affect which destination a visitor is
> sent to. Both are optional and both may be set back to `null`.

> **These two fields are the *current* attribution.** They are copied onto each
> click at the moment it is recorded, and the breakdowns in §3.8 and §3.9 read
> the copy — never these. Changing them here redirects **future** clicks to a
> new plan or customer and leaves every click already recorded exactly where it
> was. See §3.8 for what that means in practice.

---

## 3. Endpoints

### 3.1 List rotators

```
GET /api/rotators
```

Paginated, ordered by internal id (oldest first), 15 per page. Scoped to the
rotators you own. Each row carries `destinations_count`; none carry
`destinations`.

**Request**

```bash
curl -s https://your-domain.com/api/rotators \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": [
    {
      "uuid": "9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118",
      "name": "Summer Offers",
      "slug": "summer-offers",
      "status": "active",
      "default_destination_url": "https://example.com/fallback",
      "destinations_count": 3,
      "created_at": "2026-05-12T09:14:02+00:00",
      "updated_at": "2026-08-21T16:40:55+00:00"
    }
  ],
  "links": {
    "first": "https://your-domain.com/api/rotators?page=1",
    "last": "https://your-domain.com/api/rotators?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      { "url": null, "label": "&laquo; Previous", "active": false },
      { "url": "https://your-domain.com/api/rotators?page=1", "label": "1", "active": true },
      { "url": null, "label": "Next &raquo;", "active": false }
    ],
    "path": "https://your-domain.com/api/rotators",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

> A user is currently limited to **one rotator**, so expect a single row. Build
> against the paginated envelope anyway.

---

### 3.2 Get a rotator

```
GET /api/rotators/{rotator}
```

Returns the rotator with its destinations embedded, plus its lifetime traffic.
**This is the only way to enumerate destinations — there is no destination
index endpoint.**

| Parameter | In | Required | Description |
| --- | --- | --- | --- |
| `rotator` | path | yes | The rotator UUID. |

**Request**

```bash
curl -s https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118 \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": {
    "uuid": "9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118",
    "name": "Summer Offers",
    "slug": "summer-offers",
    "status": "active",
    "default_destination_url": "https://example.com/fallback",
    "destinations": [
      {
        "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77",
        "url": "https://example.com/offer-a",
        "weight": 3,
        "status": "active",
        "created_at": "2026-06-02T11:03:47+00:00",
        "updated_at": "2026-08-19T08:22:10+00:00"
      },
      {
        "uuid": "c58b0f92-6e41-4a37-9d80-11f3ba7c4e6d",
        "url": "https://example.com/offer-b",
        "weight": 1,
        "status": "active",
        "created_at": "2026-06-02T11:05:12+00:00",
        "updated_at": "2026-06-02T11:05:12+00:00"
      },
      {
        "uuid": "7d40aa13-8c55-4e29-b6f7-95c2e08d3b41",
        "url": "https://example.com/offer-c",
        "weight": 2,
        "status": "paused",
        "created_at": "2026-07-14T19:31:06+00:00",
        "updated_at": "2026-08-25T10:02:44+00:00"
      }
    ],
    "total_clicks": 4821,
    "unique_visitors": 3190,
    "created_at": "2026-05-12T09:14:02+00:00",
    "updated_at": "2026-08-21T16:40:55+00:00"
  }
}
```

`total_clicks` and `unique_visitors` are lifetime figures for the whole
rotator — there is no `range` filter on this endpoint. Both exclude bot
traffic, which is the same definition the dashboard reports as views, and both
include the fallback hits that have no destination behind them.

**Errors:** `401` no token · `404` unknown UUID **or** a rotator you do not own.

---

### 3.3 Add a destination

```
POST /api/rotators/{rotator}/destinations
```

The destination inherits its rotator and its owner from the URL, so neither can
be named in the body. There is no way to point a destination at somebody else's
rotator.

| Parameter | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| `rotator` | path | string | yes | The rotator UUID. |
| `url` | body | url | **yes** | Where the visitor is sent. `http`/`https` only, max 2048. |
| `plan_uid` | body | string | no | The plan this destination is provisioned under, max 255. Defaults to `null`. |
| `customer_uid` | body | string | no | The customer this destination belongs to, max 255. Defaults to `null`. |
| `weight` | body | integer | no | Priority tier, 1–3. Defaults to `1`. |
| `status` | body | enum | no | `active` \| `paused`. Defaults to `active`. |

**Request**

```bash
curl -s -X POST \
  https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/destinations \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
        "url": "https://example.com/offer-d",
        "plan_uid": "plan_9f2a",
        "customer_uid": "cus_4b7e",
        "weight": 2,
        "status": "active"
      }'
```

**Response — `201 Created`**

```json
{
  "data": {
    "uuid": "e21f6c07-3b4d-4a91-8c55-0d7e93b2af14",
    "url": "https://example.com/offer-d",
    "plan_uid": "plan_9f2a",
    "customer_uid": "cus_4b7e",
    "weight": 2,
    "status": "active",
    "created_at": "2026-08-29T13:47:21+00:00",
    "updated_at": "2026-08-29T13:47:21+00:00"
  }
}
```

**Errors:** `401` · `404` rotator not yours / unknown · `422` validation.

---

### 3.4 Get a destination

```
GET /api/rotators/{rotator}/destinations/{destination}
```

The destination must belong to the rotator in the URL, or the answer is `404`.

| Parameter | In | Required | Description |
| --- | --- | --- | --- |
| `rotator` | path | yes | The rotator UUID. |
| `destination` | path | yes | The destination UUID. |

**Request**

```bash
curl -s \
  https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/destinations/3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77 \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": {
    "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77",
    "url": "https://example.com/offer-a",
    "weight": 3,
    "status": "active",
    "created_at": "2026-06-02T11:03:47+00:00",
    "updated_at": "2026-08-19T08:22:10+00:00"
  }
}
```

---

### 3.5 Update a destination

```
PATCH /api/rotators/{rotator}/destinations/{destination}
PUT   /api/rotators/{rotator}/destinations/{destination}
```

Every body field is optional — send only what changes. Omitting a field leaves
it alone; this is not a read-modify-write.

Pausing a destination takes it out of the rotation on the next request, and the
rotation cursor is reset so the remaining weights stay in proportion.

| Parameter | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| `rotator` | path | string | yes | The rotator UUID. |
| `destination` | path | string | yes | The destination UUID. |
| `url` | body | url | no | `http`/`https` only, max 2048. Cannot be sent as `null`. |
| `plan_uid` | body | string \| null | no | Send `null` to detach the destination from its plan. |
| `customer_uid` | body | string \| null | no | Send `null` to detach the destination from its customer. |
| `weight` | body | integer | no | Priority tier, 1–3. |
| `status` | body | enum | no | `active` \| `paused`. |

**Request**

```bash
curl -s -X PATCH \
  https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/destinations/3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77 \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ "weight": 2, "status": "paused" }'
```

**Response — `200 OK`**

```json
{
  "data": {
    "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77",
    "url": "https://example.com/offer-a",
    "plan_uid": "plan_9f2a",
    "customer_uid": "cus_4b7e",
    "weight": 2,
    "status": "paused",
    "created_at": "2026-06-02T11:03:47+00:00",
    "updated_at": "2026-08-29T13:52:09+00:00"
  }
}
```

> `url`, `weight` and `status` reject an explicit `null`; `plan_uid` and
> `customer_uid` accept one, because clearing them is how a destination stops
> being attributed. Omitting any field still leaves it alone.

> There is **no destination delete endpoint**. Set `status` to `paused` instead.

---

### 3.6 Destination stats (headline figures)

```
GET /api/rotators/{rotator}/destinations/{destination}/stats
```

The numbers behind a destination's summary panel: clicks, unique visitors, the
rotator traffic those came out of, and how long the destination has been live.

**Bot traffic is excluded**, so these match what the dashboard calls views.

| Parameter | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| `rotator` | path | string | yes | The rotator UUID. |
| `destination` | path | string | yes | The destination UUID. |
| `range` | query | enum | no | Reporting window. **Defaults to `all_time`.** |

`range` accepts: `today`, `last_7_days`, `last_30_days`, `last_6_months`,
`this_year`, `all_time`.

**Request**

```bash
curl -s \
  "https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/destinations/3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77/stats?range=last_30_days" \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": {
    "destination": {
      "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77",
      "url": "https://example.com/offer-a",
      "weight": 3,
      "status": "active",
      "created_at": "2026-06-02T11:03:47+00:00",
      "updated_at": "2026-08-19T08:22:10+00:00"
    },
    "range": {
      "key": "last_30_days",
      "start": "2026-07-31",
      "end": "2026-08-29",
      "granularity": "day"
    },
    "kpis": {
      "clicks_received": {
        "value": 4820,
        "previous_value": 4115,
        "indicator": { "rate": 17.1, "position": "up" }
      },
      "unique_visitors": {
        "value": 3946,
        "previous_value": 3502,
        "indicator": { "rate": 12.7, "position": "up" }
      },
      "traffic_received": {
        "value": 9640,
        "previous_value": 9880,
        "indicator": { "rate": 2.4, "position": "down" }
      },
      "active_since": {
        "date": "2026-06-02",
        "days": 88
      }
    }
  }
}
```

**Reading the payload**

- `range.start` / `range.end` are dates in the app's display timezone, echoed
  back so the client never has to recompute the window.
- `range.granularity` is the bucket size the chart endpoint would use for this
  range: `hour`, `day`, `week`, or `month`.
- `traffic_received` is the **rotator's** total traffic in the window — the pool
  this destination was competing for, not its own clicks.
- `indicator.rate` is a **magnitude**, always positive. Direction lives in
  `position` (`up` / `down` / `flat`). Do not infer direction from the sign.
- `indicator.rate` is **`null`** when the range has no baseline — which is only
  `all_time`. That is how a client tells "did not move" (`rate: 0`) apart from
  "there is nothing to compare against" (`rate: null`).
- Growth from a zero baseline reports `rate: 100.0`.
- `active_since.days` is whole days between the destination's creation and now.

**Same call with the default range** (`all_time`, no baseline):

```json
{
  "data": {
    "destination": { "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77", "url": "https://example.com/offer-a", "weight": 3, "status": "active", "created_at": "2026-06-02T11:03:47+00:00", "updated_at": "2026-08-19T08:22:10+00:00" },
    "range": {
      "key": "all_time",
      "start": "2026-06-02",
      "end": "2026-08-29",
      "granularity": "month"
    },
    "kpis": {
      "clicks_received":  { "value": 14207, "previous_value": 0, "indicator": { "rate": null, "position": "flat" } },
      "unique_visitors":  { "value": 11488, "previous_value": 0, "indicator": { "rate": null, "position": "flat" } },
      "traffic_received": { "value": 28914, "previous_value": 0, "indicator": { "rate": null, "position": "flat" } },
      "active_since":     { "date": "2026-06-02", "days": 88 }
    }
  }
}
```

---

### 3.7 Destination analytics (chart)

```
GET /api/rotators/{rotator}/destinations/{destination}/chart
```

The chart series plus the tiles beside it. The headline figures live on the
stats endpoint — the two are split because they open on different windows and
neither should pay for the other's queries.

| Parameter | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| `rotator` | path | string | yes | The rotator UUID. |
| `destination` | path | string | yes | The destination UUID. |
| `range` | query | enum | no | Reporting window; also sets the bucket size. **Defaults to `last_30_days`.** |

> Note the different default: **stats defaults to `all_time`, chart defaults to
> `last_30_days`.** Always send `range` explicitly if you want the two panels
> to agree.

**Bucket size per range**

| `range` | `granularity` | `bucket` key format |
| --- | --- | --- |
| `today` | `hour` | `2026-08-29 14:00` |
| `last_7_days` | `day` | `2026-08-29` |
| `last_30_days` | `day` | `2026-08-29` |
| `last_6_months` | `week` | `2026-08-24` (the Monday of that week) |
| `this_year` | `month` | `2026-08` |
| `all_time` | `month` | `2026-08` |

**Request**

```bash
curl -s \
  "https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/destinations/3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77/chart?range=last_7_days" \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": {
    "destination": {
      "uuid": "3a7d81e5-9c02-4bb6-8f14-2d6e5c908a77",
      "url": "https://example.com/offer-a",
      "weight": 3,
      "status": "active",
      "created_at": "2026-06-02T11:03:47+00:00",
      "updated_at": "2026-08-19T08:22:10+00:00"
    },
    "range": {
      "key": "last_7_days",
      "start": "2026-08-23",
      "end": "2026-08-29",
      "granularity": "day"
    },
    "series": [
      { "bucket": "2026-08-23", "clicks": 148, "unique_visitors": 121 },
      { "bucket": "2026-08-24", "clicks": 203, "unique_visitors": 176 },
      { "bucket": "2026-08-25", "clicks": 189, "unique_visitors": 160 },
      { "bucket": "2026-08-26", "clicks": 0,   "unique_visitors": 0 },
      { "bucket": "2026-08-27", "clicks": 231, "unique_visitors": 198 },
      { "bucket": "2026-08-28", "clicks": 217, "unique_visitors": 185 },
      { "bucket": "2026-08-29", "clicks": 96,  "unique_visitors": 84 }
    ],
    "tiles": {
      "click_through_rate": {
        "value": 49.68,
        "previous_value": 46.12,
        "unit": "percent",
        "indicator": { "rate": 7.7, "position": "up" }
      },
      "avg_daily_clicks": {
        "value": 154.9,
        "previous_value": 141.3,
        "unit": "count",
        "indicator": { "rate": 9.6, "position": "up" }
      },
      "top_country": {
        "name": "US",
        "visitor_rate": 38.4
      },
      "top_device": {
        "name": "mobile",
        "visitor_rate": 61.2
      }
    }
  }
}
```

**Reading the payload**

- `series` is **zero-filled**. Every bucket in the window is present in order,
  even when nothing happened — plot it as-is and do not compress gaps.
- `bucket` is a string key, not a timestamp. Parse it against the format table
  above, keyed off `range.granularity`.
- `click_through_rate` is the share of the rotator's traffic pool this
  destination actually captured, as a percent. A heavily weighted destination
  sitting below its share is under-delivering — that is what this number exists
  to surface. It is **not** a landing-page conversion rate.
- `avg_daily_clicks` is clicks divided by days in the window, rounded to 1 dp.
- `top_country.name` is a country code; `top_device.name` is one of `desktop`,
  `mobile`, `tablet`, `bot` (bots are excluded from these counts, so `bot`
  should not appear in practice).
- Both dimension tiles return `{ "name": null, "visitor_rate": null }` when there
  is no traffic in the window. **Handle the null case.**
- `visitor_rate` is a percentage of the destination's unique visitors, clamped
  to a maximum of `100.0`.
- The `indicator` object follows the same rules as on the stats endpoint,
  including `rate: null` on `all_time`.

---

### 3.8 Traffic by plan

```
GET /api/rotators/{rotator}/traffic-by-plans
```

Totals the rotator's clicks per `plan_uid`, busiest plan first.

| Parameter | In | Required | Description |
| --- | --- | --- | --- |
| `rotator` | path | yes | The rotator UUID. |

**Request**

```bash
curl -s https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/traffic-by-plans \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": [
    { "plan_uid": "plan_9f2a", "clicks": 3104 },
    { "plan_uid": "plan_1c88", "clicks": 962 }
  ]
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `plan_uid` | string | Never `null` — see the exclusions below. |
| `clicks` | integer | Lifetime clicks across every destination on that plan, bots excluded. |

- **Lifetime figures.** There is no `range` filter yet.
- **Ordered by `clicks` descending, then `plan_uid` ascending.** The tiebreak is
  what makes the order stable across two requests over identical data.
- **Two kinds of traffic are left out**, rather than collected under a `null`
  key: clicks recorded while their destination carried no `plan_uid`, and the
  fallback hits that had no destination at all. The rows therefore do **not**
  add up to the rotator's `total_clicks`.
- An empty `data` array means nothing is attributed yet, not that there was no
  traffic.

#### Attribution is point in time

Each click is **stamped** with the destination's `plan_uid` and `customer_uid`
at the moment it is recorded. The breakdown groups those stamps; it never looks
at what the destination carries now.

What follows from that:

- **Moving a destination to another plan does not move its history.** Traffic
  stays credited to the plan that earned it, and the new plan starts from zero.
- **A plan no destination carries any more still appears**, for as long as it
  has clicks behind it.
- **Attributing a destination for the first time is not retroactive.** Clicks it
  served while unattributed stay out of the breakdown permanently.
- **Corrections do not propagate either.** A `plan_uid` that was set wrong stays
  wrong in the history recorded under it; fixing the destination only affects
  clicks from that point on.

This is deliberate — these figures are meant to survive being used for payouts,
where silently re-attributing past traffic is worse than a stale label.

**Errors:** `401` no token · `404` unknown UUID **or** a rotator you do not own.

---

### 3.9 Traffic by member

```
GET /api/rotators/{rotator}/traffic-by-members
```

The same breakdown, grouped by `customer_uid` instead. The path says *members*
and the payload says `customer_uid`: the column is named for the identifier it
stores.

| Parameter | In | Required | Description |
| --- | --- | --- | --- |
| `rotator` | path | yes | The rotator UUID. |

**Request**

```bash
curl -s https://your-domain.com/api/rotators/9f1c2b6a-4d3e-4f8a-bc21-7e5a09d4c118/traffic-by-members \
  -H "Authorization: Bearer 1|K9xQmT4pW2vLb8nZaR6yH3jC5sD7fG0eU1iO9kP2" \
  -H "Accept: application/json"
```

**Response — `200 OK`**

```json
{
  "data": [
    { "customer_uid": "cus_4b7e", "clicks": 2210 },
    { "customer_uid": "cus_0a31", "clicks": 1856 }
  ]
}
```

Every note in §3.8 applies here unchanged — including the point-in-time
stamping — with `customer_uid` in place of `plan_uid`.

**Errors:** `401` no token · `404` unknown UUID **or** a rotator you do not own.

---

## 4. Errors

| Status | When | Body |
| --- | --- | --- |
| `401` | Missing, malformed, or revoked token | `{ "message": "Unauthenticated." }` |
| `404` | Unknown UUID, a rotator you do not own, or a destination that belongs to a different rotator | `{ "message": "" }` (the message is not stable — key on the status code) |
| `422` | Validation failure | see below |
| `500` | Server error | `{ "message": "Server Error" }` |

Cross-owner access is always `404`, never `403`. Do not treat `404` as proof a
record does not exist.

**`422` example** — `POST /api/rotators/{rotator}/destinations` with a bad URL
scheme and an out-of-range weight:

```json
{
  "message": "The url field must be a valid URL. (and 1 more error)",
  "errors": {
    "url": [
      "The url field must be a valid URL."
    ],
    "weight": [
      "The weight field must be between 1 and 3."
    ]
  }
}
```

**`422` example** — an unrecognised `range` on a report endpoint:

```json
{
  "message": "The selected range is invalid.",
  "errors": {
    "range": [
      "The selected range is invalid."
    ]
  }
}
```

---

## 5. Enum reference

| Enum | Values | Used by |
| --- | --- | --- |
| Rotator status | `active`, `paused` | `Rotator.status` |
| Destination status | `active`, `paused` | `Destination.status`, `status` body param |
| Stats range | `today`, `last_7_days`, `last_30_days`, `last_6_months`, `this_year`, `all_time` | `range` query param |
| Granularity | `hour`, `day`, `week`, `month` | `range.granularity` (read-only) |
| Indicator position | `up`, `down`, `flat` | `indicator.position` (read-only) |
| Device type | `desktop`, `mobile`, `tablet`, `bot` | `tiles.top_device.name` (read-only) |

---

## 6. Endpoint summary

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/rotators` | List your rotators (paginated, with counts) |
| `GET` | `/api/rotators/{rotator}` | Get a rotator with its destinations embedded |
| `POST` | `/api/rotators/{rotator}/destinations` | Add a destination → `201` |
| `GET` | `/api/rotators/{rotator}/destinations/{destination}` | Get a destination |
| `PATCH` | `/api/rotators/{rotator}/destinations/{destination}` | Update a destination (partial) |
| `GET` | `/api/rotators/{rotator}/destinations/{destination}/stats` | Headline figures (default `all_time`) |
| `GET` | `/api/rotators/{rotator}/destinations/{destination}/chart` | Series + tiles (default `last_30_days`) |
| `GET` | `/api/rotators/{rotator}/traffic-by-plans` | Lifetime clicks totalled per `plan_uid` |
| `GET` | `/api/rotators/{rotator}/traffic-by-members` | Lifetime clicks totalled per `customer_uid` |

**Not exposed by the API:** creating, updating, or deleting a rotator; deleting
a destination; listing destinations directly. Rotator writes happen in the
dashboard UI at `/rotators`; destinations are retired by pausing them.

---

## 7. The public redirect

Not part of the authenticated API, but it is what the numbers above are
measuring.

```
GET /
```

The site root **is** the rotator. It picks the next destination by smooth
weighted round robin over the rotator's `active` destinations, records the
click asynchronously, and issues a `302` to the destination URL with
`Cache-Control: no-store` and a visitor-identity cookie (`rotator_vid`).

- Rotator `status` is ignored here — a `paused` rotator keeps rotating.
- With no rotator, or no active destination to resolve, the response is `404`.
- Never send an application user to `/`; it drops them onto an affiliate offer.
  The marketing page is `/welcome`.
