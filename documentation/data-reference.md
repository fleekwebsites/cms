# Data reference

Enums, field constraints, content formatting, and standard error shapes.

---

## Article enums

### `type`

| Value | Label | Notes |
|-------|-------|-------|
| `blog` | Blog | Default layout on receiving site |
| `faq` | FAQ | Requires `layout` selection in CMS form |

### `layout`

| Value | Label | Description |
|-------|-------|-------------|
| `default` | Default | Standard single-column article |
| `featured` | Featured | Hero image with prominent title |
| `magazine` | Magazine | Editorial two-column reading layout |
| `minimal` | Minimal | Clean text-first layout |
| `split` | Split | Sidebar alongside article body |

Required for FAQ articles in the CMS. Blogs use `default` on the receiving site regardless of CMS layout unless you implement layout switching remotely.

### `status` (CMS only)

| Value | Label |
|-------|-------|
| `draft` | Draft |
| `published` | Published |

`published_at` is set automatically on first publish.

---

## CMS form validation (articles)

Rules from `StoreArticleRequest` / `UpdateArticleRequest`:

| Field | Rules |
|-------|-------|
| `type` | Required; `blog` or `faq` |
| `title` | Required; max **60** characters |
| `site_id` | Required; must exist and be active |
| `site_category_id` | Required; must belong to selected site |
| `author_id` | Required; must belong to selected site |
| `content` | Required; HTML string |
| `status` | Required; `draft` or `published` |
| `excerpt` | Optional; max **2,000** characters |
| `keywords` | Optional; max **5,000** characters |
| `featured_image` | Optional file upload; image; max **5 MB** |
| `featured_image_url` | Optional URL; max **2,048** characters |
| `layout` | Required when `type` is `faq` |

---

## Author fields (CMS)

| Field | Rules |
|-------|-------|
| `site_id` | Required |
| `name` | Required; unique per site; max **255** |
| `credentials` | Optional; max **255** |
| `bio` | Optional; max **2,000** |

---

## Category fields (CMS)

| Field | Rules |
|-------|-------|
| `name` | Required; max **120**; unique per site |

---

## Site fields (CMS)

| Field | Rules |
|-------|-------|
| `name` | Required |
| `api_endpoint` | Required; valid URL; max **2,048** characters |
| `is_active` | Optional boolean |

API keys are auto-generated (`cms_` + 40 random characters), encrypted at rest, and hidden from JSON serialization.

---

## Content formatting

Before publish, article HTML passes through `ArticleContentFormatter::forPublish()`:

### Normalization steps

1. **Unwrap Quill embeds** — `ql-html-block` and `ql-html-inline` spans restored to raw HTML.
2. **Normalize lists** — Quill `data-list` attributes converted to proper `<ul>` / `<ol>` nesting.
3. **Alignment** — `ql-align-*` classes converted to inline `text-align` styles.
4. **Indent** — `ql-indent-N` classes converted to inline `padding-left`.
5. **Remove Quill UI** — `ql-ui` spans stripped from list items.
6. **Embed local images** — `<img src="/storage/...">` converted to `data:image/...;base64,...` data URIs.

### Color and highlight

Quill color classes (e.g. `ql-color-e60000`) are converted to inline `color` styles. Background highlights are preserved as `background-color` inline styles.

### What remote sites receive

- Semantic HTML with inline CSS where needed.
- `content_format` is always `html`.
- No Quill-specific class names in the final payload (for colors/alignment that were normalized).

### Reading time

`reading_time_minutes` is calculated from word count via `ReadingTimeEstimator`. Minimum value is **1** when content is non-empty.

---

## Featured image encoding

`PublishableImageEncoder` only embeds images that exist on the CMS **local public disk**:

- URLs matching `/storage/{path}` where the file exists in `storage/app/public/`.

External URLs (e.g. `https://cdn.example/photo.jpg`) cannot be embedded and fall back to `featured_image_url` in the payload.

---

## HTTP timeouts

| Setting | Value | Config key |
|---------|-------|------------|
| Connect timeout | 5 s | `cms.http.connect_timeout` |
| Request timeout | 15 s | `cms.http.timeout` |

---

## Standard Laravel validation error shape

For CMS form and JSON requests using Form Requests:

```http
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json
```

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "site_category_id": ["The selected site category id is invalid."]
  }
}
```

---

## Remote receiver error summary

| Status | Meaning |
|--------|---------|
| `200`–`299` | Success (CMS logs as successful publish) |
| `400` | Empty or invalid JSON body |
| `401` | Invalid `X-API-Key` |
| `405` | Non-POST request |
| `413` | Decoded image exceeds size limit (content receiver) |
| `422` | Missing/invalid field or rejected `featured_image_url` |
| `500` | Server/storage/database failure |

---

## Identifiers

| Entity | Identifier | Scope |
|--------|------------|-------|
| Article | `uuid` (36-char) | Globally unique; used for remote upsert |
| Article | `slug` | URL-friendly; not guaranteed unique across sites |
| Author | `id` (integer) | Unique within CMS; same ID sent to remote site |
| Category | `id` (integer) | Unique within CMS; same ID sent to remote site |
| Site | `id` (integer) | CMS internal |

When displaying articles on the remote site, join `author_id` → `cms_authors.id` and `site_category_id` → `cms_categories.id`.

---

## Publish log status (CMS)

| Value | Meaning |
|-------|---------|
| `success` | Remote returned 2xx |
| `failed` | Network error or non-2xx response |
