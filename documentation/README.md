# Content CMS — API Documentation

This CMS publishes blogs and FAQs from a central Laravel application to one or more **remote sites**. It also exposes a small set of **JSON endpoints** for the authenticated web UI (rich-text image uploads, author/category pickers).

There is no public REST API with bearer tokens. Remote integration uses **shared API keys** over HTTPS.

## Documentation map

| Document | Audience | Description |
|----------|----------|-------------|
| [Authentication](authentication.md) | CMS users & integrators | Session login for the CMS UI; `X-API-Key` for remote receivers |
| [CMS internal API](cms-internal-api.md) | Frontend / CMS developers | JSON endpoints used by the article editor and forms |
| [Outbound publishing API](outbound-publishing-api.md) | Remote site developers | What the CMS **sends** when publishing content, authors, and categories |
| [Remote site receiver API](remote-site-receiver-api.md) | Remote site developers | Contract your receiving site must implement (request/response, validation, errors) |
| [Data reference](data-reference.md) | Everyone | Enums, field types, content formatting, HTTP timeouts, error shapes |

## Architecture overview

```
┌─────────────────────┐         POST /api/cms/content          ┌──────────────────────┐
│   Content CMS       │  ───────────────────────────────────►  │   Remote site        │
│   (Laravel app)     │         X-API-Key + JSON body          │   (your receiver)    │
│                     │                                        │                      │
│  - Articles         │  ── POST /api/cms/authors ──────────►  │  - Store articles    │
│  - Authors          │  ── POST /api/cms/categories ───────►  │  - Store authors     │
│  - Site categories  │                                        │  - Store categories  │
└─────────────────────┘                                        └──────────────────────┘
```

### Typical integration flow

1. **Deploy receiver scripts** on the remote site (see `examples/` in this repository).
2. **Register a site** in the CMS with `api_endpoint` pointing at the content receiver, e.g. `https://yoursite.com/api/cms/content`.
3. **Copy the generated API key** from the CMS site settings into the receiver configuration (`CMS_API_KEY`).
4. **Create authors and categories** in the CMS — they are pushed to the remote site automatically.
5. **Write and publish an article** — the CMS POSTs the full article JSON to the content endpoint.

### Endpoint URL derivation

When a site’s `api_endpoint` ends with `/content`, the CMS derives sibling URLs:

| Resource | CMS calls | Example |
|----------|-----------|---------|
| Content | `{api_endpoint}` as configured | `https://yoursite.com/api/cms/content` |
| Authors | Replace trailing `content` with `authors/` | `https://yoursite.com/api/cms/authors/` |
| Categories | Replace trailing `content` with `categories/` | `https://yoursite.com/api/cms/categories/` |

If `api_endpoint` does **not** end with `/content`, the resource name is appended: `{api_endpoint}/authors/`.

### Example receiver files

Reference implementations live in the repository:

| File | Purpose |
|------|---------|
| `examples/receive-cms-content.php` | Article/content receiver (SQLite or custom storage) |
| `examples/receive-cms-authors.php` | Author sync receiver |
| `examples/receive-cms-categories.php` | Category sync receiver |
| `examples/cms-receiver-common.php` | Shared auth, validation, and database helpers |
| `examples/webuzo-apache/api-cms-rewrite.htaccess` | Apache rewrite rules for clean URLs |

### Configuration (CMS)

| Setting | Location | Default |
|---------|----------|---------|
| HTTP connect timeout | `config/cms.php` | 5 seconds |
| HTTP request timeout | `config/cms.php` | 15 seconds |

Only **active** sites receive outbound requests. Inactive sites are skipped.

## Quick examples

### Publish article (CMS → remote)

```http
POST https://yoursite.com/api/cms/content
Accept: application/json
Content-Type: application/json
X-API-Key: cms_abc123...
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000-3

{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "how-to-prepare-for-nursing-exams",
  "type": "blog",
  "layout": "default",
  "title": "How to Prepare for Nursing Exams",
  "content": "<p>Essential tips...</p>",
  "content_format": "html",
  "reading_time_minutes": 4,
  "site_category_id": 12,
  "author_id": 7,
  "site_id": 3,
  "site_name": "Nursing Elites",
  "published_at": "2026-09-10 09:30:00"
}
```

### Upload editor image (browser → CMS)

```http
POST /articles/images
Cookie: laravel_session=...
X-CSRF-TOKEN: ...
Content-Type: multipart/form-data

file=<image binary>
```

Response:

```json
{ "location": "https://cms.example/storage/article-images/abc.png" }
```

## Support

- Publish attempts are logged per article and site in the CMS **Publish history** panel.
- Request payloads in logs omit large `content` and `featured_image_base64` fields (replaced with byte counts).
- Response bodies in logs are truncated to 10,000 characters.
