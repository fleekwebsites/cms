# Client ID mapping

Authors, categories, and topics use a **client ID** system so the CMS can assign stable identifiers before your site returns its own primary keys. Articles use a **UUID** instead and do not participate in this mapping table.

Understanding this pattern is essential for taxonomy POST handlers and for resolving foreign keys on articles.

---

## Why client IDs exist

When a user creates a category in the CMS:

1. The CMS generates a random client id in the range **100 000 – 999 999**.
2. The CMS POSTs `{ "id": 456789, "name": "NP Programs" }` to your site.
3. Your site creates the record, assigns its own primary key (e.g. `7`), and responds with both ids.
4. The CMS stores the mapping and uses your primary key on subsequent outbound payloads.

Articles always use a **36-character UUID** as the stable identifier; no client-id mapping step is required.

---

## Create flow (taxonomy)

### Request from CMS

```http
POST /api/categories/
X-API-Key: cms_...
Idempotency-Key: category-456789
Content-Type: application/json

{
  "id": 456789,
  "name": "NP Programs"
}
```

### Required response

```http
HTTP/1.1 201 Created
Content-Type: application/json

{
  "status": "accepted",
  "id": 7,
  "client_id": 456789,
  "name": "NP Programs"
}
```

| Field | Meaning |
|-------|---------|
| `id` | **Your site’s primary key** — used in later CMS payloads |
| `client_id` | The id the CMS sent in the request body — must be echoed back |
| `status` | `"accepted"` on success (reference receivers) |

The same pattern applies to **authors** and **topics** (topics also include `site_category_id` in request and response).

### Update flow

When updating an existing record, the CMS sends **your primary key** (or a previously mapped id) in the `id` field. Upsert by matching `id` **or** `client_id` on your side.

---

## How the CMS uses mappings

After a successful create:

1. CMS records `client_id → remote id` internally per site and resource type.
2. Before sending articles or topics, CMS **translates** foreign keys:
   - `site_category_id` → your category primary key
   - `author_id` → your author primary key
   - `topic_id` → your topic primary key

Your receiver should also resolve foreign keys flexibly:

- Accept `site_category_id` on topic create as **either** your category primary key **or** the CMS client id, and resolve to your internal category before storing.

The reference `topics.php` receiver resolves categories with “match by primary key OR client_id”.

---

## Article foreign keys

Blog article payloads include:

```json
{
  "site_category_id": 7,
  "author_id": 3,
  "topic_id": 12
}
```

These integers are **your site’s primary keys** after mapping—not CMS client ids (unless no mapping exists yet, which should be rare once create succeeded).

When rendering an article on your frontend, join:

- `author_id` → your stored author record
- `site_category_id` → your stored category record
- `topic_id` → your stored topic record (blogs only)

The CMS does **not** embed author names, category names, or topic names in article JSON. Resolve display labels from your own stored taxonomy.

---

## Pending writes and ID translation

If your site is unreachable when a category is created, the CMS **queues** the write. When the category is later accepted and mapping is recorded, queued payloads that reference the client id are **rewritten** to use your primary key before retry.

Implement POST responses with correct `id` and `client_id` pairs so this translation can complete.

---

## Receiver checklist

- [ ] On taxonomy POST **create**, treat body `id` as CMS client id; assign your own primary key
- [ ] Response includes both `id` (yours) and `client_id` (CMS’s)
- [ ] Upsert lookups match **either** primary key **or** client id
- [ ] Category references on topics accept client id or primary key
- [ ] List GET endpoints return your primary key in each item’s `id` field
- [ ] Articles keyed only by `uuid` — no client id mapping

---

## Related documentation

- [Remote site receiver API](remote-site-receiver-api.md) — per-resource POST bodies
- [Outbound traffic from CMS](outbound-publishing-api.md) — when creates and updates are sent
- [Integration guide](integration-guide.md) — end-to-end setup
