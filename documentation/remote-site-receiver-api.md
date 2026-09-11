# Remote site receiver API

Your site must implement the HTTP endpoints below so the CMS can read and write content. This document is the **receiver contract**: methods, headers, JSON shapes, validation, and error codes.

Implementation reference: `examples/mysqli-remote/` (see [Deployment](deployment.md)).

> **Note:** This document describes the **HTTP API only**. How you persist data on your server is your choice; internal storage schemas are not part of the integration contract.

---

## Base URL

Configure the CMS with:

```
https://yourdomain.com/api/
```

Resource URLs replace the trailing `content` segment:

| Resource | Base path |
|----------|-----------|
| Articles | `{api_endpoint}` — e.g. `https://yourdomain.com/api/` |
| Authors | `https://yourdomain.com/api/authors/` |
| Categories | `https://yourdomain.com/api/categories/` |
| Topics | `https://yourdomain.com/api/topics/` |

---

## Shared requirements

### Authentication

Every request requires:

```http
X-API-Key: cms_...
```

Invalid key → `401`:

```json
{ "error": "Invalid API key." }
```

See [Authentication](authentication.md).

### Content type

| Method | Body |
|--------|------|
| GET | No body |
| POST | JSON (`Content-Type: application/json`) |
| DELETE | No body |

Empty or invalid JSON on POST → `400`:

```json
{ "error": "Empty request body." }
```

```json
{ "error": "Invalid JSON body." }
```

### Success status codes

| Code | Typical use |
|------|-------------|
| `200` | GET success, DELETE success |
| `201` | POST upsert success |

The CMS treats any **2xx** response as success.

### Error status codes

| Code | Meaning |
|------|---------|
| `400` | Empty or invalid JSON |
| `401` | Invalid API key |
| `404` | Single resource not found (GET/DELETE) |
| `405` | HTTP method not allowed |
| `413` | Decoded image too large |
| `422` | Validation failure |
| `429` | Rate limited (CMS may queue and retry) |
| `500` | Server or storage failure |

Error bodies use a single string field:

```json
{ "error": "Human-readable message." }
```

### Idempotency

POST and DELETE requests include `Idempotency-Key`. Store it for audit; upsert safely on duplicate delivery. See [Authentication](authentication.md#idempotency-key).

---

## Articles

Articles are keyed by **UUID** (36 characters). All saves are full upserts.

### List articles

```http
GET /api/
X-API-Key: cms_...
Accept: application/json
```

Optional query parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | string | Filter by `blog` or `faq` |
| `status` | string | Filter by `draft`, `complete`, or `published` |

**Response `200`** — JSON array of article objects (may be empty):

```json
[
  {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "slug": "example-post",
    "type": "blog",
    "layout": "default",
    "status": "draft",
    "title": "Example Post",
    "content": "<p>Hello</p>",
    "content_format": "html",
    "reading_time_minutes": 1,
    "site_category_id": 7,
    "author_id": 3,
    "topic_id": 12,
    "site_id": 1,
    "site_name": "Example Site",
    "editor_user_id": 2,
    "editor_name": "Alex Editor",
    "published_at": null,
    "featured_image_url": null,
    "received_at": "2026-09-11 10:00:00",
    "updated_at": "2026-09-11 10:00:00"
  }
]
```

Optional fields may be omitted when null/empty. Integer foreign keys reference **your site’s** taxonomy primary keys.

### Get one article

```http
GET /api/{uuid}
```

**Response `200`** — single article object (same shape as list items).

**Response `404`** — not found.

### Upsert article

```http
POST /api/
Idempotency-Key: {uuid}
Content-Type: application/json
```

#### Required fields

| Field | Type | Validation |
|-------|------|------------|
| `uuid` | string | Non-empty; UUID format recommended |
| `slug` | string | Non-empty |
| `type` | string | `blog` or `faq` |
| `layout` | string | Non-empty (`default`, `featured`, `magazine`, `minimal`, `split`) |
| `title` | string | Non-empty |
| `content` | string | Non-empty HTML |
| `content_format` | string | Expected `html` |

#### Common optional fields

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | `draft`, `complete`, or `published` |
| `excerpt` | string | Summary |
| `keywords` | string | SEO keywords |
| `reading_time_minutes` | integer | Minutes to read |
| `site_category_id` | integer | Category primary key on your site |
| `author_id` | integer | Author primary key on your site |
| `topic_id` | integer | Topic primary key (blogs only) |
| `site_id` | integer | CMS connection id (informational) |
| `site_name` | string | CMS connection name |
| `editor_user_id` | integer | CMS user id of editor |
| `editor_name` | string | CMS user display name |
| `published_at` | string | UTC `Y-m-d H:i:s` when published |

#### Status and `published_at`

| `status` | `published_at` |
|----------|----------------|
| `draft` | Omit or null |
| `complete` | Omit or null |
| `published` | Set to publication timestamp |

If `status` is omitted, infer from `published_at` (present → published, absent → draft).

#### Featured image (embedded)

Preferred delivery uses Base64, not a remote URL:

| Field | Type | Required with image |
|-------|------|---------------------|
| `featured_image_base64` | string | Yes |
| `featured_image_mime` | string | Yes |
| `featured_image_filename` | string | No |

- Decode Base64 (no data-URI prefix in payload).
- Allowed MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`.
- Recommended max decoded size: **8 MB**.

If only `featured_image_url` is sent without embedded data, the reference receiver returns `422`. The CMS should re-upload images through the editor so they can be embedded.

#### Inline content images

Scan `content` for `<img src="data:image/...;base64,...">`, store files locally, and rewrite `src` to your public URLs before saving.

#### Success response `201`

```json
{
  "status": "accepted",
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "example-post",
  "featured_image_url": "/blogs/images/550e8400-e29b-41d4-a716-446655440000/featured.jpg",
  "content_format": "html"
}
```

`featured_image_url` may be `null` when no image was provided.

#### Upsert behavior

Match existing row by `uuid`. Repeated POST with the same uuid updates the record.

### Delete article

```http
DELETE /api/{uuid}
Idempotency-Key: {uuid}
```

**Response `200`:**

```json
{
  "status": "deleted",
  "uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response `404`** if not found. Delete associated media files if stored separately.

---

## Authors

Authors use the [client ID mapping](id-mapping.md) pattern on create.

### List authors

```http
GET /api/authors/
```

**Response `200`** — JSON array:

```json
[
  {
    "id": 3,
    "name": "Elena Marsh",
    "credentials": "DNP, FNP-BC",
    "bio": "Educator and clinician."
  }
]
```

`credentials` and `bio` may be omitted or null.

### Get one author

```http
GET /api/authors/{id}
```

`{id}` may be your primary key or a previously stored CMS client id (reference receiver matches either).

### Upsert author

```http
POST /api/authors/
Idempotency-Key: author-{id}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | CMS client id on create; your primary key on update |
| `name` | string | Yes | Non-empty after trim |
| `credentials` | string \| null | No | Short credential line |
| `bio` | string \| null | No | Biography |

**Response `201`:**

```json
{
  "status": "accepted",
  "id": 3,
  "client_id": 456789,
  "name": "Elena Marsh",
  "credentials": "DNP, FNP-BC"
}
```

Always return **`id`** (your primary key) and **`client_id`** (CMS-assigned id on create).

### Delete author

```http
DELETE /api/authors/{id}
Idempotency-Key: author-{id}
```

**Response `200`:** `{ "status": "deleted", "id": 3 }`

---

## Categories

Same client ID pattern as authors. The CMS does **not** send a slug; generate URL slugs on your site from `name` if needed.

### List categories

```http
GET /api/categories/
```

**Response `200`:**

```json
[
  { "id": 7, "name": "NP Programs" }
]
```

### Get one category

```http
GET /api/categories/{id}
```

### Upsert category

```http
POST /api/categories/
Idempotency-Key: category-{id}
```

| Field | Type | Required |
|-------|------|----------|
| `id` | integer | Yes |
| `name` | string | Yes |

**Response `201`:**

```json
{
  "status": "accepted",
  "id": 7,
  "client_id": 456789,
  "name": "NP Programs",
  "slug": "np-programs"
}
```

`slug` is optional in the response but useful for your frontend.

### Delete category

```http
DELETE /api/categories/{id}
```

**Response `200`:** `{ "status": "deleted", "id": 7 }`

---

## Topics

Topics belong to a **category**. Blog articles reference topics; FAQ articles do not.

### List topics

```http
GET /api/topics/?site_category_id=7
```

| Query | Required | Description |
|-------|----------|-------------|
| `site_category_id` | Recommended | Filter to one category; accept primary key or CMS client id |

Without filter, return all topics (reference receiver) or require the parameter—if you require it, document that clearly for your operators.

**Response `200`:**

```json
[
  {
    "id": 12,
    "name": "Exam prep",
    "site_category_id": 7
  }
]
```

### Get one topic

```http
GET /api/topics/{id}
```

### Upsert topic

```http
POST /api/topics/
Idempotency-Key: topic-{id}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | CMS client id on create |
| `site_category_id` | integer | Yes | Category primary key or client id |
| `name` | string | Yes | Non-empty after trim |

Resolve `site_category_id` to an existing category before insert. Return `422` if the category does not exist.

**Response `201`:**

```json
{
  "status": "accepted",
  "id": 12,
  "client_id": 654321,
  "site_category_id": 7,
  "name": "Exam prep"
}
```

### Delete topic

```http
DELETE /api/topics/{id}
```

**Response `200`:** `{ "status": "deleted", "id": 12 }`

---

## Rendering published content

- `content` is **HTML**, not Markdown.
- Render without escaping HTML tags intended for display.
- Inline styles from the CMS editor (alignment, color, highlights) should be preserved.
- Resolve `author_id`, `site_category_id`, and `topic_id` from your stored taxonomy—names are not duplicated in article JSON.

---

## Recommended implementation order

1. **Categories** and **authors** — POST + GET list
2. **Topics** — depends on categories
3. **Articles** — references taxonomy ids and handles images
4. **DELETE** handlers — when CMS admins remove content

---

## Testing with cURL

### Category create

```bash
curl -X POST "https://yourdomain.com/api/categories/" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-API-Key: cms_your_key" \
  -H "Idempotency-Key: category-100001" \
  -d '{"id": 100001, "name": "Test Category"}'
```

### Topic create

```bash
curl -X POST "https://yourdomain.com/api/topics/" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: cms_your_key" \
  -H "Idempotency-Key: topic-100002" \
  -d '{"id": 100002, "site_category_id": 7, "name": "Test Topic"}'
```

### Article upsert

```bash
curl -X POST "https://yourdomain.com/api/" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: cms_your_key" \
  -H "Idempotency-Key: 00000000-0000-4000-8000-000000000001" \
  -d '{
    "uuid": "00000000-0000-4000-8000-000000000001",
    "slug": "test-article",
    "type": "blog",
    "layout": "default",
    "status": "draft",
    "title": "Test Article",
    "content": "<p>Hello world</p>",
    "content_format": "html",
    "reading_time_minutes": 1,
    "site_category_id": 7,
    "author_id": 3,
    "topic_id": 12
  }'
```

---

## Related documentation

- [Integration guide](integration-guide.md)
- [Client ID mapping](id-mapping.md)
- [Outbound traffic from CMS](outbound-publishing-api.md)
- [Data reference](data-reference.md)
