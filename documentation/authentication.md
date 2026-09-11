# Authentication

Remote integration uses a **shared API key** sent on every CMS → site request. The CMS web UI uses a separate **session** model and is not part of your receiver implementation.

---

## Remote site receivers (API key)

Used by: all CMS traffic to your site (articles, authors, categories, topics).

### Required header

```http
X-API-Key: cms_<40-character-random-string>
```

### Key lifecycle

| Event | Action |
|-------|--------|
| Site created | CMS generates a new key (`cms_` + 40 random characters) |
| Admin regenerates key | Old key stops working immediately; update your receiver config |
| Key storage (CMS) | Encrypted at rest; shown in admin UI for copy/paste |

### Validation on your receiver

Compare keys with a **timing-safe** function:

```php
hash_equals(CMS_API_KEY, $incomingKey);
```

**Invalid or missing key:**

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{ "error": "Invalid API key." }
```

Never echo the expected key in error messages.

---

## Idempotency key

The CMS sends:

```http
Idempotency-Key: <unique-string>
```

on every **POST** and **DELETE** request.

### Formats used by the CMS

| Resource | Idempotency-Key format | Example |
|----------|------------------------|---------|
| Article | `{uuid}` | `550e8400-e29b-41d4-a716-446655440000` |
| Author | `author-{id}` | `author-456789` |
| Category | `category-{id}` | `category-123456` |
| Topic | `topic-{id}` | `topic-654321` |

The `{id}` in author/category/topic keys is the **CMS client id** sent in the POST body, not necessarily your site’s internal primary key.

### Recommended receiver behavior

1. **Store** the idempotency key with the upserted record (for audit).
2. **Treat duplicate deliveries as safe** — upsert by stable identifier (`uuid` for articles; `id` or `client_id` for taxonomy).
3. You do **not** need to reject duplicate keys with a special status; returning `201` on repeat upsert is acceptable.

Articles are always upserted by `uuid`. Authors, categories, and topics should upsert by matching either your primary key or the CMS `client_id`.

---

## Request headers summary

| Header | Required | Description |
|--------|----------|-------------|
| `X-API-Key` | Yes | Shared secret |
| `Content-Type` | Yes on POST | `application/json` |
| `Accept` | Sent by CMS | `application/json` |
| `Idempotency-Key` | Sent on POST/DELETE | Deduplication / audit |

---

## Security recommendations

1. **HTTPS only** in production. Do not accept API keys over plain HTTP.
2. **Rotate keys** via CMS admin if a key is exposed in logs or version control.
3. **Do not log** full API keys, request bodies containing Base64 images, or session cookies.
4. **Restrict methods** — receivers should only allow the HTTP methods they implement (GET, POST, DELETE).
5. **Validate JSON** before processing — return `400` for empty or malformed bodies.

---

## What is NOT used on remote endpoints

| Mechanism | Used? |
|-----------|-------|
| `Authorization: Bearer …` | No |
| OAuth / OpenID | No |
| CMS session cookies | No |
| CSRF tokens | No (server-to-server only) |

---

## CMS web application (session) — for reference

The CMS browser UI authenticates with email/password and a session cookie. JSON routes under the CMS domain (image upload, inline category/topic create) require that session plus CSRF.

**External integrators should not call CMS UI routes.** Build receivers on your site instead. See [CMS internal API](cms-internal-api.md) only if you extend the CMS frontend itself.

| Topic | Detail |
|-------|--------|
| Login | `POST /login` with email + password |
| Session lifetime | 120 minutes (configurable) |
| CSRF | Required on browser POST requests (`419` if missing) |
| Roles | `admin` (full access), `writer` (own articles, delegated site access) |
