# Deployment

This page describes how to deploy the **reference PHP receivers** included in this repository. You may adapt them or reimplement the same HTTP contract in another language.

The reference scripts demonstrate authentication, validation, upsert logic, and image handling, they are not the only valid implementation.


---

## Recommended URL structure

| Public URL | Handler |
|------------|---------|
| `GET/POST {API ENDPOINT}` | `{API ENDPOINT}` |
| `GET/DELETE {API ENDPOINT}/{uuid}` | `{API ENDPOINT}` |
| `GET/POST {API ENDPOINT}/authors/` | `{API ENDPOINT}/authors/` |
| `GET/DELETE {API ENDPOINT}/authors/{id}` | `{API ENDPOINT}/authors/` |
| `GET/POST {API ENDPOINT}/categories/` | `{API ENDPOINT}/categories/` |
| `GET/DELETE {API ENDPOINT}/categories/{id}` | `{API ENDPOINT}/categories/` |
| `GET/POST {API ENDPOINT}/topics/` | `{API ENDPOINT}/topics/` |
| `GET/DELETE {API ENDPOINT}/topics/{id}` | `{API ENDPOINT}/topics/` |

Register the CMS connection with:

```
 {API ENDPOINT} -> https://yourdomain.com/api/ 
```

---

### Article image storage

In `{API ENDPOINT}`, configure:

| Constant | Purpose |
|----------|---------|
| `CMS_STORAGE_ROOT` | Filesystem directory for decoded images (must be writable) |
| `CMS_PUBLIC_MEDIA_BASE` | URL prefix served to browsers, e.g. `/blogs/images` |
| `CMS_MAX_IMAGE_BYTES` | Max decoded image size (default 8 MB) |

Serve stored files from your web server or CDN at the public base path.

---

## CMS registration checklist

1. Deploy all four receiver scripts and `.htaccess`.
2. Set `CMS_API_KEY` on the server.
3. Verify mysqli/database connectivity from PHP (your environment).
4. In CMS admin → create connection:
   - **Name:** your site display name
   - **API endpoint:** `https://yourdomain.com/api/`
5. Copy API key into receiver config.
6. Run manual GET tests (see [Integration guide](integration-guide.md#step-5--verify-read-access-get)).

---

## Verify deployment

```bash
# Categories list
curl -sS -o /dev/null -w "%{http_code}" \
  "https://yourdomain.com/api/categories/" \
  -H "Accept: application/json" \
  -H "X-API-Key: cms_your_key"

# Create category (replace body id with any integer 100000-999999)
curl -sS -X POST "https://yourdomain.com/api/categories/" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-API-Key: cms_your_key" \
  -H "Idempotency-Key: category-test-1" \
  -d '{"id": 100001, "name": "Test Category"}'
```

Expect `200` on GET (possibly with `[]`) and `201` on POST with `status: "accepted"` and both `id` and `client_id`.

---

## Custom implementations

If you do not use the PHP examples:

1. Implement the same URLs, methods, headers, and JSON shapes documented in [Remote site receiver API](remote-site-receiver-api.md).
2. Follow [Client ID mapping](id-mapping.md) for taxonomy creates.
3. Do **not** depend on internal CMS database details—only the HTTP contract matters.

---

## Related documentation

- [Integration guide](integration-guide.md)
- [Remote site receiver API](remote-site-receiver-api.md)
- [Authentication](authentication.md)
