# Outbound publishing API

When content is published or metadata is created/updated in the CMS, the application sends HTTP **POST** requests to the configured remote site. This document describes **what the CMS sends**.

Implementation references:

- Articles: `app/Actions/PublishArticleToSites.php`
- Authors: `app/Actions/PublishAuthorToSite.php`
- Categories: `app/Actions/PublishSiteCategoryToSite.php`
- HTTP client: `app/Support/SiteApiClient.php`

---

## Common request properties

All outbound calls share:

| Property | Value |
|----------|-------|
| Method | `POST` |
| `Accept` | `application/json` |
| `Content-Type` | `application/json` (implicit via JSON body) |
| `X-API-Key` | Site’s API key (`cms_…`) |
| `Idempotency-Key` | See per-endpoint table below |
| Connect timeout | 5 seconds (`config('cms.php')`) |
| Request timeout | 15 seconds |

Only sites with `is_active = true` receive requests.

### Success criteria

The CMS treats any **2xx** HTTP status as success. The response body is stored in publish logs (truncated to 10,000 characters).

### Failure handling

- Network errors and non-2xx responses are logged with `status: failed`, HTTP code, and error message.
- Author/category sync failures surface as flash messages in the CMS UI but do not roll back local records.

---

## 1. Publish article (content)

### URL

The site’s `api_endpoint` field **exactly as configured**, e.g.:

```
https://yoursite.com/api/endpoint
```

### Idempotency-Key

```
{article-uuid}-{site-id}
```

Example: `550e8400-e29b-41d4-a716-446655440000-3`

### When triggered

- User clicks **Publish** on an article (`POST /articles/{article}/publications`).
- Article status is set to `published` and `published_at` is set if not already present.
- Only the article’s assigned site receives the request (not a multi-site fan-out per action).

### JSON body

#### Required fields

| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | 36-character article UUID (stable identifier for upserts) |
| `slug` | string | URL slug |
| `type` | string | `blog` or `faq` |
| `layout` | string | `default`, `featured`, `magazine`, `minimal`, or `split` |
| `title` | string | Article title (max 60 chars in CMS form) |
| `content` | string | Normalized HTML (see [Content formatting](data-reference.md#content-formatting)) |
| `content_format` | string | Always `html` |
| `reading_time_minutes` | integer | Estimated reading time (minimum `1` for non-empty content) |

#### Optional fields

Included only when present in the CMS record:

| Field | Type | Description |
|-------|------|-------------|
| `excerpt` | string | Short summary (max 2,000 chars in CMS) |
| `keywords` | string | Comma-separated keywords (max 5,000 chars) |
| `site_category_id` | integer | CMS category ID on the target site |
| `author_id` | integer | CMS author ID on the target site |
| `site_id` | integer | CMS site ID |
| `site_name` | string | Human-readable site name |
| `published_at` | string | UTC timestamp `Y-m-d H:i:s` |

#### Featured image

If the article has a `featured_image_url`:

1. **Preferred:** CMS embeds the image if it is stored locally under `/storage/…`:

| Field | Type | Description |
|-------|------|-------------|
| `featured_image_base64` | string | Base64-encoded binary (no data-URI prefix) |
| `featured_image_mime` | string | MIME type, e.g. `image/jpeg` |
| `featured_image_filename` | string | Original filename, e.g. `featured.jpg` |

2. **Fallback:** If the image cannot be read from local storage (e.g. external URL only), the CMS sends:

| Field | Type | Description |
|-------|------|-------------|
| `featured_image_url` | string | Original URL |

> **Note:** The reference receiver **rejects** `featured_image_url` without embedded Base64 (`422`). Re-upload images through the CMS so they are stored locally before publishing.

#### Fields NOT sent

The CMS does **not** embed author name, credentials, bio, or category name in the article payload. Remote sites must resolve `author_id` and `site_category_id` from previously synced author/category records.

### Example payload

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "how-to-prepare-for-nursing-exams",
  "type": "blog",
  "layout": "magazine",
  "title": "How to Prepare for Nursing Exams",
  "content": "<p style=\"text-align:center\">Nursing exams require disciplined study.</p><p><span style=\"color:#e60000\">Important:</span> start early.</p>",
  "content_format": "html",
  "reading_time_minutes": 4,
  "excerpt": "Essential tips for nursing exam success",
  "keywords": "nursing, exams, study tips",
  "site_category_id": 12,
  "author_id": 7,
  "site_id": 3,
  "site_name": "Nursing Elites",
  "published_at": "2026-09-10 09:30:00",
  "featured_image_base64": "iVBORw0KGgoAAAANSUhEUgAA...",
  "featured_image_mime": "image/jpeg",
  "featured_image_filename": "featured.jpg"
}
```

### cURL example

```bash
curl -X POST "https://yoursite.com/api/endpoint" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: cms_your_api_key_here" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000-3" \
  -d @article-payload.json
```

---

## 2. Sync author

### URL

Derived from content endpoint:

```
{base}/authors/
```

Example: `https://yoursite.com/api/endpoint/authors/`

### Idempotency-Key

```
author-{author-id}
```

### When triggered

- Admin creates an author (`POST /authors`).
- Admin updates an author (`PUT/PATCH /authors/{author}`).

### JSON body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | CMS author ID (use as primary key on remote site) |
| `name` | string | Yes | Display name |
| `credentials` | string \| null | No | e.g. `DNP, FNP-BC` |
| `bio` | string \| null | No | Author biography (max 2,000 chars in CMS) |

### Example

```json
{
  "id": 7,
  "name": "Elena Marsh",
  "credentials": "DNP, FNP-BC",
  "bio": "Family nurse practitioner and educator."
}
```

---

## 3. Sync category

### URL

```
{base}/categories/
```

Example: `https://yoursite.com/api/endpoint/categories/`

### Idempotency-Key

```
category-{category-id}
```

### When triggered

- Admin creates a category on a site (`POST /sites/{site}/categories`).

Category deletion is **local only** — no DELETE request is sent to the remote site.

### JSON body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | CMS category ID |
| `name` | string | Yes | Category name (max 120 chars in CMS) |

### Example

```json
{
  "id": 12,
  "name": "NP Programs"
}
```

---

## Publish logs (CMS-side)

Each article publish attempt creates a `publish_logs` record:

| Field | Description |
|-------|-------------|
| `request_payload` | Copy of sent JSON; `content` and `featured_image_base64` replaced with `[omitted: N bytes]` |
| `response_code` | HTTP status or `null` on connection failure |
| `response_payload` | Response body (JSON pretty-printed, max 10k chars) |
| `status` | `success` or `failed` |
| `error_message` | HTTP reason phrase or exception message on failure |

View logs on the article **Publish history** section in the CMS UI.
