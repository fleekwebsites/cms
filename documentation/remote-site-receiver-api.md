# Remote site receiver API

Your receiving site must implement HTTP endpoints that accept the payloads described in [Outbound publishing API](outbound-publishing-api.md). This document is the **receiver contract**: validation rules, responses, and error codes.


---

## Deployment

### Recommended URL structure

Configure the CMS site `api_endpoint` as:

```
https://yourdomain.com/api/endpoint
```

Use Apache rewrite (`examples/webuzo-apache/api-cms-rewrite.htaccess`):

| Public URL | Function |
|------------|--------|
| `POST /api/endpoint` | `receive article content from CMS` |
| `POST /api/cms/authors` | `receive-cms-authors from CMS` |
| `POST /api/cms/categories` | `receive CMS Blog categories` |

Direct script URLs also work:

```
https://yourdomain.com/api/endpoint/receive-cms-content.php
```

### Configuration

Set `CMS_API_KEY` in the receiver to match the key shown in the CMS site settings.

---

## Shared requirements (all endpoints)

### Method

`POST` only (except optional media GET on content receiver).

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Content-Type` | Yes | `application/json` |
| `X-API-Key` | Yes | Must match configured `CMS_API_KEY` |
| `Idempotency-Key` | No | Stored for deduplication/audit |

### Authentication errors

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{ "error": "Invalid API key." }
```

### JSON parse errors

```http
HTTP/1.1 400 Bad Request

{ "error": "Empty request body." }
```

```http
HTTP/1.1 400 Bad Request

{ "error": "Invalid JSON body." }
```

### Method errors

```http
HTTP/1.1 405 Method Not Allowed

{ "error": "Method not allowed. Use POST." }
```

---

## POST `/api/endpoint`

Receives and stores a published article.

### Required body fields

| Field | Type | Validation |
|-------|------|------------|
| `uuid` | string | Non-empty; reference receiver also validates UUID format |
| `slug` | string | Non-empty |
| `type` | string | Non-empty (`blog` or `faq` expected) |
| `layout` | string | Non-empty |
| `title` | string | Non-empty |
| `content` | string | Non-empty HTML |

### Optional body fields

| Field | Type | Description |
|-------|------|-------------|
| `excerpt` | string | Summary |
| `keywords` | string | SEO keywords |
| `content_format` | string | Defaults to `html` if omitted |
| `reading_time_minutes` | integer | Minutes to read |
| `site_category_id` | integer | Foreign key to synced category |
| `author_id` | integer | Foreign key to synced author |
| `site_id` | integer | Originating CMS site ID |
| `site_name` | string | Originating site name |
| `published_at` | string | Publication timestamp |
| `featured_image_base64` | string | Base64 image data |
| `featured_image_mime` | string | Required with `featured_image_base64` |
| `featured_image_filename` | string | Suggested filename |

### Featured image handling

**Accepted:** `featured_image_base64` + `featured_image_mime` (+ optional `featured_image_filename`).

- Decoded size must not exceed **8 MB** (`CMS_MAX_IMAGE_BYTES` in reference receiver).
- Allowed MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`.
- Receiver stores the file locally and sets `featured_image_url` to a public path.

**Rejected:** `featured_image_url` alone (remote URL without embedded file):

```http
HTTP/1.1 422 Unprocessable Entity

{
  "error": "featured_image_url without embedded file is not accepted. Re-upload the image in the CMS so it can be sent as featured_image_base64.",
  "featured_image_url": "https://..."
}
```

### Inline content images

The reference receiver scans `content` HTML for `<img src="data:image/...;base64,...">` tags, writes files to local storage, and rewrites `src` to public URLs. This ensures the published site does not depend on CMS-hosted image URLs.

### Success response `201`

```json
{
  "status": "accepted",
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "how-to-prepare-for-nursing-exams",
  "featured_image_url": "/media/cms/articles/550e8400-e29b-41d4-a716-446655440000/featured.jpg",
  "content_format": "html"
}
```

`featured_image_url` is `null` when no featured image was provided.

### Validation errors `422`

```json
{ "error": "Missing or invalid field: title." }
```

### Storage errors `500`

```json
{ "error": "Failed to create storage directory." }
```

### Upsert behavior

Articles are upserted by `uuid`. Re-publishing the same article updates the existing record.

### Optional: serve stored media

The content receiver example supports:

```http
GET /api/cms/receive-cms-content.php?media={relative-path}
```

Returns binary image data or `404` JSON. Production deployments should serve `/media/cms/articles/*` as static files instead.

---

## POST `/api/cms/authors`

Syncs an author record from the CMS.

### Body fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `id` | integer | Yes | Positive integer (≥ 1) |
| `name` | string | Yes | Non-empty after trim |
| `credentials` | string \| null | No | Trimmed; empty → `null` |
| `bio` | string \| null | No | Trimmed; empty → `null` |

### Success response `201`

```json
{
  "status": "accepted",
  "id": 7,
  "name": "Elena Marsh",
  "credentials": "DNP, FNP-BC"
}
```

### Upsert behavior

`INSERT … ON DUPLICATE KEY UPDATE` (MySQL) or `ON CONFLICT` (SQLite). Same `id` updates name, credentials, and bio.

### Database schema (reference)

**MySQL** (`expected fields`):

```json
{
    Authors{
      name,
      credentials,
      bio,
      idempotency_key,
    }
}

```

---

## POST `/api/cms/categories`

Syncs a category record from the CMS.

### Body fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `id` | integer | Yes | Positive integer (≥ 1) |
| `name` | string | Yes | Non-empty after trim |

### Success response `201`

```json
{
  "status": "accepted",
  "id": 12,
  "name": "NP Programs"
}
```

### Upsert behavior

Same as authors — upsert by `id`.

### Database schema (reference)

```json
{
  categories {
      name 
      idempotency_key
  }
}

```

---

## Implementing your own receiver

If you do not use the PHP examples, your application must:

1. **Authenticate** every request via `X-API-Key`.
2. **Parse JSON** and return `400` on empty/invalid bodies.
3. **Validate required fields** and return `422` with a clear `error` message.
4. **Upsert** articles by `uuid`, authors/categories by `id`.
5. **Decode and store** `featured_image_base64` and inline content images locally.
6. **Return `201`** with `status: "accepted"` on success.
7. **Resolve** `author_id` and `site_category_id` when rendering articles on your frontend.

### Rendering published HTML

- Content is **HTML**, not Markdown.
- Render with unescaped HTML (`{!! $content !!}` in Blade, or equivalent).
- Do not strip inline `style` attributes if you want colors, highlights, and table formatting to display.
- Quill alignment classes are converted to inline `text-align` styles before publish.

### Ordering of operations

1. Sync **categories** and **authors** first (via CMS admin).
2. Publish **articles** that reference those IDs.

If an article references an unknown `author_id` or `site_category_id`, your site should handle the missing reference gracefully (e.g. omit byline or show a default).

---

## Testing your receiver

### Manual cURL (content)

```bash
curl -X POST "https://yourdomain.com/api/cms/content" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: cms_your_key" \
  -H "Idempotency-Key: test-1" \
  -d '{
    "uuid": "00000000-0000-4000-8000-000000000001",
    "slug": "test-article",
    "type": "blog",
    "layout": "default",
    "title": "Test Article",
    "content": "<p>Hello world</p>",
    "content_format": "html",
    "reading_time_minutes": 1
  }'
```

### From the CMS

1. Register your site with the receiver URL.
2. Create a category and author (check remote DB).
3. Create a draft article, assign site/category/author.
4. Click **Publish** and review **Publish history** for HTTP status and response.
