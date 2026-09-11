# Outbound traffic from CMS

When users work in the CMS, the application sends HTTP requests **to your site**. This document describes **when** those requests fire, **where** they go, and **what** JSON bodies contain.

Your site implements the receiver side; see [Remote site receiver API](remote-site-receiver-api.md) for the full contract.

Implementation references in the CMS codebase:

- `app/Support/RemoteSiteGateway.php` — all outbound calls
- `app/Support/SiteApiClient.php` — HTTP client
- `app/Support/RemoteIdMapper.php` — client id → your primary key translation
- `app/Support/PendingRemoteWriteQueue.php` — retry queue

---

## Common properties

Every outbound call:

| Property | Value |
|----------|-------|
| Method | GET, POST, or DELETE (per operation) |
| `Accept` | `application/json` |
| `Content-Type` | `application/json` on POST |
| `X-API-Key` | Connection API key (`cms_…`) |
| `Idempotency-Key` | On POST and DELETE |
| Connect timeout | 5 seconds |
| Request timeout | 15 seconds |

Only connections with **active** status receive traffic.

### Success

Any **2xx** HTTP status is treated as success.

On taxonomy POST success, the CMS reads `id` and `client_id` from the response body and stores the mapping. See [Client ID mapping](id-mapping.md).

### Failure and retry

| Condition | CMS behavior |
|-----------|--------------|
| Connection error | Queue write; show warning to user |
| HTTP `5xx` | Queue write; show warning |
| HTTP `429` | Queue write; show warning |
| HTTP `4xx` (except 429) | Fail immediately; show error message |
| Inactive site | No request sent |

Queued writes retry via `php artisan cms:flush-pending-writes` (also schedulable).

---

## URL derivation

Given `api_endpoint = https://yourdomain.com/api/`:

| Resource | URL |
|----------|-----|
| Articles | `{api_endpoint}` | `https://yourdomain.com/api/` |
| Authors | `{base}authors/` | `https://yourdomain.com/api/authors/` |
| Categories | `{base}categories/` | `https://yourdomain.com/api/categories/` |
| Topics | `{base}topics/` | `https://yourdomain.com/api/topics/` |

Single-article GET/DELETE append the UUID to `{api_endpoint}`: `{api_endpoint}/{uuid}`. Taxonomy GET/DELETE append the id: `/authors/{id}`, etc.

---

## Articles

### When the CMS sends

| User action | Effect |
|-------------|--------|
| Create article | POST full payload, `status` from form (usually `draft`) |
| Update article | POST full payload with same `uuid` |
| Mark as complete | POST with `status: "complete"`, no `published_at` |
| Publish | POST with `status: "published"` and `published_at` |
| Delete article (admin) | DELETE `{api_endpoint}/{uuid}` |

Articles are **always stored on your site**, including drafts—not only on publish.

### Idempotency-Key

```
{uuid}
```

Example: `550e8400-e29b-41d4-a716-446655440000`

### POST body

Built by `RemoteArticlePayload`. Key fields:

| Field | Notes |
|-------|-------|
| `uuid` | Stable identifier |
| `slug`, `type`, `layout`, `title`, `content`, `content_format` | Required |
| `status` | `draft`, `complete`, or `published` |
| `reading_time_minutes` | Estimated; minimum 1 for non-empty content |
| `site_category_id`, `author_id` | Translated to your primary keys |
| `topic_id` | Blogs only; translated to your primary key |
| `site_id`, `site_name` | CMS connection metadata |
| `editor_user_id`, `editor_name` | CMS editor metadata |
| `published_at` | Only when `status` is `published` |
| `excerpt`, `keywords` | When provided |
| Featured image | `featured_image_base64` + mime (+ filename) when embeddable |

Taxonomy **names are not included**—only integer foreign keys.

Before send, `RemoteIdMapper::translateArticlePayload()` resolves category, author, and topic ids.

### GET (CMS reads)

| Call | Purpose |
|------|---------|
| `GET {api_endpoint}` | Article list in CMS workspace |
| `GET {api_endpoint}/{uuid}` | Article detail, edit form, publish actions |

Optional query: `type`, `status`.

---

## Authors

### When the CMS sends

| User action | HTTP |
|-------------|------|
| Create author | POST |
| Update author | POST (same `id`) |
| Delete author | DELETE |

### Idempotency-Key

```
author-{id}
```

On create, `{id}` is the CMS-generated client id (100 000 – 999 999).

### POST body

```json
{
  "id": 456789,
  "name": "Elena Marsh",
  "credentials": "DNP, FNP-BC",
  "bio": "Optional biography."
}
```

### GET (CMS reads)

| Call | Purpose |
|------|---------|
| `GET /authors/` | Author list and article form dropdown |

---

## Categories

### When the CMS sends

| User action | HTTP |
|-------------|------|
| Create category (form or categories page) | POST |
| Update category | POST |
| Delete category | DELETE |

### Idempotency-Key

```
category-{id}
```

### POST body

```json
{
  "id": 456789,
  "name": "NP Programs"
}
```

Slug is **not** sent; generate on your site if needed.

Before topic or article send, `site_category_id` values are translated via stored mappings.

### GET (CMS reads)

| Call | Purpose |
|------|---------|
| `GET /categories/` | Category list, article form, categories page |

---

## Topics

### When the CMS sends

| User action | HTTP |
|-------------|------|
| Add topic from article form | POST |
| (No dedicated topic admin page) | |

### Idempotency-Key

```
topic-{id}
```

### POST body

```json
{
  "id": 654321,
  "site_category_id": 7,
  "name": "Exam prep"
}
```

`site_category_id` is translated to your category primary key when a mapping exists.

### GET (CMS reads)

| Call | Purpose |
|------|---------|
| `GET /topics/?site_category_id={id}` | Topic dropdown for selected category |

The CMS passes the category id currently selected in the form (your primary key or client id before resolution on read—your list endpoint should return consistent primary keys).

---

## Pending write queue

When a write is queued:

1. Payload is stored locally on the CMS (large article bodies may spill to disk).
2. User sees a warning that the site was unreachable; changes will sync later.
3. `cms:flush-pending-writes` retries POST/DELETE with the same idempotency key.
4. When a taxonomy mapping arrives, pending payloads referencing client ids are rewritten to primary keys before retry.

Your receiver should remain **idempotent** so retries do not create duplicates.

---

## Article workflow (status)

```
draft  ──►  complete  ──►  published
  │              │              │
  POST           POST           POST
  (save)    (mark complete)  (publish)
```

| Status | Sent to your site | `published_at` |
|--------|-------------------|----------------|
| `draft` | Yes, on save | Omitted |
| `complete` | Yes, on “Mark as complete” | Omitted |
| `published` | Yes, on “Publish” | UTC timestamp set |

Publish is blocked in the CMS until status is `complete`.

---

## DELETE operations

| Resource | URL | When |
|----------|-----|------|
| Article | `DELETE {api_endpoint}/{uuid}` | Admin deletes from CMS |
| Author | `DELETE /authors/{id}` | Admin removes author |
| Category | `DELETE /categories/{id}` | Admin removes category |

Topics are not deleted from a dedicated CMS UI in the current version; implement DELETE for API completeness.

---

## What the CMS stores locally

The CMS keeps **connection settings**, **users**, **delegations**, **client-id mappings**, and **pending failed writes**. It does **not** keep a copy of article or taxonomy content after a successful sync—that lives on your site.

---

## Related documentation

- [Remote site receiver API](remote-site-receiver-api.md)
- [Client ID mapping](id-mapping.md)
- [Integration guide](integration-guide.md)
- [Data reference](data-reference.md)
