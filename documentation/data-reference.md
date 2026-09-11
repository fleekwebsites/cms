# Data reference

Field types, enums, content formatting rules, and standard error shapes for CMS ↔ remote site integration.

---

## Article `type`

| Value | Label | Notes |
|-------|-------|-------|
| `blog` | Blog | May include `topic_id` |
| `faq` | FAQ | Requires `layout`; no `topic_id` |

---

## Article `layout`

| Value | Label |
|-------|-------|
| `default` | Default |
| `featured` | Featured |
| `magazine` | Magazine |
| `minimal` | Minimal |
| `split` | Split |

Required in the CMS form when `type` is `faq`. Blogs default to `default` unless you implement layout switching on your site.

---

## Article `status`

| Value | Label | On your site |
|-------|-------|--------------|
| `draft` | Draft | Editing in progress; no `published_at` |
| `complete` | Complete | Ready to publish; no `published_at` |
| `published` | Published | Live; `published_at` set (UTC `Y-m-d H:i:s`) |

Workflow: `draft` → `complete` → `published`. The CMS sends POST on each transition.

---

## Identifiers

| Entity | Identifier | Notes |
|--------|------------|-------|
| Article | `uuid` (36-char string) | Globally unique; upsert key |
| Author | integer `id` | Client id on create (100 000–999 999); your primary key after mapping |
| Category | integer `id` | Same client id pattern |
| Topic | integer `id` | Same client id pattern |
| CMS connection | integer `site_id` | Informational field on articles only |

See [Client ID mapping](id-mapping.md).

---

## Article fields (remote payload)

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `uuid` | string | Yes | Stable across updates |
| `slug` | string | Yes | URL slug |
| `type` | string | Yes | `blog` or `faq` |
| `layout` | string | Yes | See layout enum |
| `title` | string | Yes | Max 60 chars in CMS form |
| `content` | string | Yes | HTML |
| `content_format` | string | Yes | Always `html` from CMS |
| `status` | string | No | `draft`, `complete`, `published` |
| `reading_time_minutes` | integer | No | Min 1 when content non-empty |
| `excerpt` | string | No | Max 2 000 chars in CMS |
| `keywords` | string | No | Max 5 000 chars in CMS |
| `site_category_id` | integer | No | Your category primary key |
| `author_id` | integer | No | Your author primary key |
| `topic_id` | integer | No | Blogs only; your topic primary key |
| `site_id` | integer | No | CMS connection id |
| `site_name` | string | No | Connection display name |
| `editor_user_id` | integer | No | CMS user id |
| `editor_name` | string | No | CMS user name |
| `published_at` | string | No | UTC datetime when published |
| `featured_image_base64` | string | No | Base64 binary, no data-URI prefix |
| `featured_image_mime` | string | No | With Base64 payload |
| `featured_image_filename` | string | No | Suggested filename |

---

## Author fields (remote payload)

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `id` | integer | Yes | Client id or your primary key |
| `name` | string | Yes | Max 255 chars in CMS |
| `credentials` | string \| null | No | Max 255 chars |
| `bio` | string \| null | No | Max 2 000 chars |

---

## Category fields (remote payload)

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `id` | integer | Yes | Client id or your primary key |
| `name` | string | Yes | Max 120 chars |

Slug is generated on your site, not sent by the CMS.

---

## Topic fields (remote payload)

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `id` | integer | Yes | Client id or your primary key |
| `site_category_id` | integer | Yes | Category primary key or client id |
| `name` | string | Yes | Max 120 chars |

---

## CMS connection fields (admin)

| Field | Constraints |
|-------|-------------|
| `name` | Required |
| `api_endpoint` | Required URL; typically ends with `/api/` or  `/api/content/` |
| `is_active` | Boolean; inactive sites receive no traffic |

API keys: prefix `cms_`, 40 random characters, encrypted in CMS storage.

---

## Content formatting

Before articles are sent, HTML passes through normalization:

1. **Quill embeds** — custom embed spans restored to raw HTML
2. **Lists** — `data-list` attributes converted to proper `<ul>` / `<ol>`
3. **Alignment** — editor alignment classes → inline `text-align` styles
4. **Indent** — indent classes → inline `padding-left`
5. **UI chrome** — editor-only spans removed
6. **Local images** — CMS `/storage/…` images in content → `data:image/…;base64,…` data URIs

Color and highlight classes become inline `color` / `background-color` styles.

Your receiver should decode inline data-URI images and store them locally.

---

## Featured images

When the featured image file exists on the CMS public disk (`/storage/…`), the CMS sends Base64 + MIME. External URLs fall back to `featured_image_url` only; the reference receiver rejects that without embedded data.

Recommended max decoded size: **8 MB**.

---

## HTTP timeouts (CMS → your site)

| Setting | Default |
|---------|---------|
| Connect timeout | 5 seconds |
| Request timeout | 15 seconds |

---

## Remote receiver error summary

| Status | Meaning |
|--------|---------|
| `200`–`299` | Success |
| `400` | Empty or invalid JSON |
| `401` | Invalid `X-API-Key` |
| `404` | Resource not found |
| `405` | Method not allowed |
| `413` | Image too large |
| `422` | Validation error |
| `429` | Rate limited (CMS may queue) |
| `500` | Server failure |

Standard error shape:

```json
{ "error": "Human-readable message." }
```

---

## CMS form validation errors (UI only)

Browser/API requests to the CMS (not your receiver) may return Laravel validation errors:

```http
HTTP/1.1 422 Unprocessable Entity
```

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

---

## Related documentation

- [Remote site receiver API](remote-site-receiver-api.md)
- [Client ID mapping](id-mapping.md)
- [Outbound traffic from CMS](outbound-publishing-api.md)
