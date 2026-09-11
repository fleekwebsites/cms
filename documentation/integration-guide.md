# Integration guide

This guide walks through connecting a remote site to the Content CMS from scratch. Follow the steps in order; each step builds on the previous one.

## Prerequisites

- HTTPS on your receiving site (required in production)
- Ability to deploy PHP scripts or implement equivalent HTTP handlers in your stack
- CMS admin access to register the connection and copy the API key

## Step 1 — Choose your receiver approach

You can either:

**Option A — Reference PHP receivers (fastest)**  
Deploy PHP scripts behind Apache rewrite rules. See [Deployment](deployment.md).

**Option B — Custom implementation**  
Implement the HTTP contract in your framework of choice. Follow [Remote site receiver API](remote-site-receiver-api.md) field-for-field.

Both options must support **GET** (list/read), **POST** (upsert), and **DELETE** (remove) for each resource the CMS uses.

## Step 2 — Expose the four resource endpoints

Configure URL routing so these paths respond (example base: `https://yourdomain.com/api/`):

| Path | Methods | Purpose |
|------|---------|---------|
| `{api_endpoint}` | GET, POST | List and upsert articles |
| `{api_endpoint}/{uuid}` | GET, DELETE | Read or delete one article |
| `authors/` | GET, POST | List and upsert authors |
| `authors/{id}` | GET, DELETE | Read or delete one author |
| `categories/` | GET, POST | List and upsert categories |
| `categories/{id}` | GET, DELETE | Read or delete one category |
| `topics/` | GET, POST | List and upsert topics (`?site_category_id=` on GET) |
| `topics/{id}` | GET, DELETE | Read or delete one topic |

The CMS `api_endpoint` should be:

```
https://yourdomain.com/api/
```

The CMS derives sibling URLs by attaching with each resource name (see [Overview](README.md#endpoint-url-derivation)).

## Step 3 — Configure authentication

1. Generate or choose a secret API key on your receiver (must match what the CMS will send).
2. Validate the `X-API-Key` header on **every** request using a timing-safe comparison.
3. Return `401` with `{ "error": "Invalid API key." }` when the key is wrong.

Details: [Authentication](authentication.md).

## Step 4 — Register the site in the CMS

1. Sign in to the CMS as an admin.
2. Create a new **connection** (site).
3. Set **API endpoint** to your content URL, e.g. `https://yourdomain.com/api/`.
4. Copy the generated API key (`cms_…`) into your receiver configuration.
5. Ensure the site is **active**.

## Step 5 — Verify read access (GET)

From the CMS:

1. Open the connection workspace.
2. Navigate to **Categories** or start **Write article**.

If GET works, dropdowns populate with categories and authors from your site. If they are empty, your list endpoints may be returning `[]` (valid) or failing authentication/routing.

**Manual test:**

```bash
curl -sS "https://yourdomain.com/api/categories/" \
  -H "Accept: application/json" \
  -H "X-API-Key: cms_your_key_here"
```

Expected: HTTP `200` and a JSON array (possibly empty).

## Step 6 — Sync taxonomy (POST categories, authors, topics)

Create records from the CMS UI:

| Action in CMS | Remote endpoint | Notes |
|---------------|-----------------|-------|
| Add category | `POST /categories/` | CMS sends a client `id`; you return `id` + `client_id` |
| Add author | `POST /authors/` | Same client ID pattern |
| Add topic (article form) | `POST /topics/` | Requires `site_category_id` and `name` |

Read [Client ID mapping](id-mapping.md) before implementing POST handlers. Your receiver must:

- Accept the CMS client `id` on create
- Assign your own primary key
- Return both keys in the JSON response

## Step 7 — Save articles (POST articles)

Articles are sent to your site when:

- A writer **creates** or **updates** an article
- A writer **marks complete** (status → `complete`)
- A writer **publishes** (status → `published`, `published_at` set)

Each save is a full upsert keyed by `uuid`. Implement idempotent upsert behavior.

Blog articles include `topic_id` when a topic is selected. FAQ articles omit `topic_id` and require a `layout` value.

## Step 8 — Handle images

Article payloads may include:

- **Featured image:** `featured_image_base64`, `featured_image_mime`, optional `featured_image_filename`
- **Inline content images:** `<img src="data:image/...;base64,...">` inside `content` HTML

Your receiver should decode, store files locally, and rewrite URLs so published pages do not depend on CMS-hosted image paths.

The reference receiver rejects `featured_image_url` alone without embedded Base64. Re-upload images through the CMS editor so they are stored and embedded before publish.

## Step 9 — Test the full workflow

| Step | CMS action | Expected on your site |
|------|------------|----------------------|
| 1 | Create category | `POST /categories/` → `201` |
| 2 | Create author | `POST /authors/` → `201` |
| 3 | Create blog + topic | `POST /topics/` then `POST /` → `201` |
| 4 | Edit draft | `POST /` with same `uuid` → `201` (update) |
| 5 | Mark complete | `POST /` with `status: "complete"` |
| 6 | Publish | `POST /` with `status: "published"` and `published_at` |
| 7 | Delete article (admin) | `DELETE /{uuid}` → `200` |

## Step 10 — Production hardening

- [ ] HTTPS only; reject plain HTTP in production
- [ ] Rate limiting on receiver endpoints (optional but recommended)
- [ ] Log request method, path, and status — not full API keys or Base64 payloads
- [ ] Serve stored media from your CDN or static path
- [ ] Schedule or cron `cms:flush-pending-writes` on the CMS server for queued retries
- [ ] Document your key rotation procedure (regenerate in CMS → update receiver config)

## Troubleshooting

| Symptom | Likely cause | What to check |
|---------|--------------|---------------|
| CMS shows “remote site unreachable” | DNS, TLS, firewall, wrong URL | `curl` GET to each resource URL; CMS `api_endpoint` spelling |
| Categories load but add topic fails | Category ID mismatch, missing topics route | Receiver logs for `POST /topics/`; `.htaccess` includes topics rule |
| `401` on all requests | API key mismatch | Key in CMS site settings vs receiver config; no extra whitespace |
| `404` on POST | Rewrite rules or wrong URL | POST must hit `{api_endpoint}` exactly |
| `422` on article | Missing required field | Compare body to [Remote site receiver API](remote-site-receiver-api.md) |
| Changes appear later, not immediately | Queued write | CMS queued the request; flush pending writes after site recovery |
| Taxonomy IDs do not match | Client ID mapping | Return `client_id` in POST responses; see [Client ID mapping](id-mapping.md) |

## Next steps

- [Remote site receiver API](remote-site-receiver-api.md) — complete request/response reference
- [Outbound traffic from CMS](outbound-publishing-api.md) — triggers and idempotency keys
- [Data reference](data-reference.md) — enums and field constraints
