# Content CMS — Integration Documentation

This CMS manages content **on connected remote sites**. Articles, authors, categories, and topics are stored on each site you connect—not in the CMS database. The CMS reads and writes that content over HTTPS using a shared API key.

This documentation is written for **developers integrating a receiving site** (PHP, Node, Laravel, WordPress plugin, etc.). It describes the HTTP contract your site must implement so the CMS can list, create, update, and delete content reliably.

## Documentation map

| Page | Audience | Description |
|------|----------|-------------|
| [Integration guide](integration-guide.md) | Remote site developers | Step-by-step checklist from zero to working connection |
| [Authentication](authentication.md) | Remote site developers | `X-API-Key`, idempotency, security |
| [Remote site receiver API](remote-site-receiver-api.md) | Remote site developers | Full HTTP contract: methods, payloads, responses, errors |
| [Outbound traffic from CMS](outbound-publishing-api.md) | Remote site developers | When the CMS sends requests and what triggers each call |
| [Client ID mapping](id-mapping.md) | Remote site developers | How CMS-assigned IDs map to your site’s IDs |
| [Deployment](deployment.md) | Remote site developers | Reference PHP receivers, URL routing, configuration |
| [Data reference](data-reference.md) | Everyone | Field types, enums, content formatting, error codes |
| [CMS internal API](cms-internal-api.md) | CMS UI developers | Session JSON endpoints used by the article editor only |

## Architecture

```
┌─────────────────────────┐     GET / POST / DELETE      ┌──────────────────────────┐
│   Content CMS           │  ─────────────────────────►  │   Your remote site       │
│   (Laravel app)         │     X-API-Key + JSON         │   (receiver you build)   │
│                         │                              │                          │
│  Reads: articles,       │  ◄─────────────────────────  │  Stores: articles,       │
│  authors, categories,   │     JSON responses           │  authors, categories,    │
│  topics                 │                              │  topics, media files     │
└─────────────────────────┘                              └──────────────────────────┘
```

The CMS **does not** expose a public REST API for external clients to manage remote content. Integration happens on **your site’s URLs**. The CMS web UI uses separate session-authenticated routes (see [CMS internal API](cms-internal-api.md)).

## Endpoint URL derivation

Register each connection with an `api_endpoint` that ends in `/`, for example:

```
https://yourdomain.com/api/
```

The CMS uses the configured `api_endpoint` **directly for articles**. Taxonomy resources replace the trailing `/` segment:

| Resource | CMS calls | Example |
|----------|-----------|---------|
| Articles | `{api_endpoint}` | `https://yourdomain.com/api/` |
| Authors | `{base}authors/` | `https://yourdomain.com/api/authors/` |
| Categories | `{base}categories/` | `https://yourdomain.com/api/categories/` |
| Topics | `{base}topics/` | `https://yourdomain.com/api/topics/` |

Single-article GET/DELETE use `{api_endpoint}/{uuid}`. If `api_endpoint` does **not** end with `/` or `/`, taxonomy paths are `{api_endpoint}/authors/` etc.; articles still use `{api_endpoint}` only.

## Typical integration flow

1. **Implement receiver endpoints** on your site (or deploy the reference scripts in `examples/mysqli-remote/`).
2. **Register the site** in the CMS admin and set `api_endpoint` to your content URL (e.g. `https://yourdomain.com/api/`).
3. **Copy the generated API key** into your receiver configuration (`CMS_API_KEY` or equivalent).
4. **Verify GET** — open the CMS connection workspace; categories and authors should load from your site.
5. **Create taxonomy** — add categories, authors, and (for blogs) topics from the CMS; confirm POST requests arrive on your site.
6. **Save and publish articles** — drafts sync immediately; publishing sets `status` to `published` and `published_at`.

See the [Integration guide](integration-guide.md) for a detailed checklist and troubleshooting.

## Reference implementation

Working PHP examples live in the repository:

| Path | Purpose |
|------|---------|
| `{API ENDPOINT}` | Article list, upsert, delete, image handling |
| `{API ENDPOINT}/authors` | Author sync |
| `{API ENDPOINT}/categories` | Category sync |
| `{API ENDPOINT}/topics` | Topic sync (scoped to category) |

See [Deployment](deployment.md) for setup instructions.

## Configuration (CMS side)

| Setting | Default | Description |
|---------|---------|-------------|
| HTTP connect timeout | 5 seconds | Time to establish connection |
| HTTP request timeout | 15 seconds | Time to complete request |

Only **active** sites receive outbound traffic. Inactive sites are skipped.

When your site is temporarily unavailable, the CMS **queues** failed writes and retries them via the `cms:flush-pending-writes` scheduled command. See [Outbound traffic from CMS](outbound-publishing-api.md#pending-write-queue).

## Quick example — upsert an article

```http
POST `{API ENDPOINT}`
Accept: application/json
Content-Type: application/json
X-API-Key: cms_abc123...
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000

{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "how-to-prepare-for-nursing-exams",
  "type": "blog",
  "layout": "default",
  "status": "draft",
  "title": "How to Prepare for Nursing Exams",
  "content": "<p>Essential tips...</p>",
  "content_format": "html",
  "reading_time_minutes": 4,
  "site_category_id": 12,
  "author_id": 7,
  "topic_id": 3,
  "site_id": 1,
  "site_name": "Example Site",
  "editor_user_id": 2,
  "editor_name": "Alex Editor"
}
```

Success response:

```json
{
  "status": "accepted",
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "how-to-prepare-for-nursing-exams",
  "featured_image_url": null,
  "content_format": "html"
}
```

## Support and debugging

- Use your web server access logs and application logging on the receiver side to confirm requests arrive.
- In the CMS, article save/publish responses surface as flash messages; queued writes indicate the site was unreachable or returned a retryable error.
- Run `php artisan cms:flush-pending-writes` on the CMS server to retry queued operations manually.
