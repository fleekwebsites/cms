# CMS internal API

JSON endpoints consumed by the **authenticated CMS web UI**. These routes are for CMS frontend development only—not for remote site integration.

Remote sites implement [Remote site receiver API](remote-site-receiver-api.md) instead.

All routes require a valid **session** and (for `POST`) a **CSRF token**.

Base URL: your CMS `APP_URL`.

---

## POST `/articles/images`

Upload an image from the rich-text editor.

### Authorization

User must be allowed to create articles.

### Request

```http
POST /articles/images
Content-Type: multipart/form-data
X-CSRF-TOKEN: {token}
Cookie: laravel_session={session}
```

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `file` | file | Yes | Image; max 5 MB |

### Success `200`

```json
{
  "location": "https://cms.example.com/storage/article-images/xYz123.png"
}
```

On article save, locally stored images in HTML are embedded as Base64 in the outbound payload to your site.

---

## GET `/sites/{site}/categories/options`

List categories from the **remote site** for dropdowns.

```http
GET /sites/{site}/categories/options
Accept: application/json
Cookie: laravel_session={session}
```

### Success `200`

```json
[
  { "id": 7, "name": "NP Programs" }
]
```

### Errors

| Status | Cause |
|--------|-------|
| `401` | Not logged in |
| `403` | No access to site |
| `503` | Remote site unreachable |

Proxies to `GET {remote}/categories/` with the site API key.

---

## GET `/sites/{site}/authors/options`

List authors from the remote site.

```http
GET /sites/{site}/authors/options
Accept: application/json
```

### Success `200`

```json
[
  { "id": 3, "name": "Elena Marsh · DNP, FNP-BC" }
]
```

Display line includes credentials when set.

---

## GET `/sites/{site}/categories/{categoryId}/topics`

List topics for a category from the remote site.

```http
GET /sites/{site}/categories/7/topics
Accept: application/json
```

### Success `200`

```json
[
  { "id": 12, "name": "Exam prep" }
]
```

---

## POST `/sites/{site}/categories`

Create a category on the remote site (HTML form or AJAX).

### JSON body (inline create from article form)

```json
{ "name": "NP Programs" }
```

### Success `201`

```json
{
  "id": 7,
  "client_id": 456789,
  "name": "NP Programs",
  "queued": false
}
```

When the remote site is unreachable, `queued: true` and HTTP `202` may be returned.

---

## POST `/sites/{site}/topics`

Create a topic on the remote site (inline from article form).

### JSON body

```json
{
  "site_category_id": 7,
  "name": "Exam prep"
}
```

### Success `201`

```json
{
  "id": 12,
  "client_id": 654321,
  "name": "Exam prep",
  "site_category_id": 7,
  "queued": false
}
```

---

## HTML routes that trigger outbound traffic

These return redirects, not JSON:

| Route | Method | Remote effect |
|-------|--------|---------------|
| `/sites/{site}/articles` | POST | POST article to remote |
| `/sites/{site}/articles/{uuid}` | PUT | POST article update |
| `/sites/{site}/articles/{uuid}` | DELETE | DELETE article |
| `/sites/{site}/articles/{uuid}/completion` | POST | POST status `complete` |
| `/sites/{site}/articles/{uuid}/publications` | POST | POST status `published` |
| `/sites/{site}/authors` | POST | POST author |
| `/sites/{site}/authors/{id}` | PUT | POST author update |
| `/sites/{site}/categories` | POST | POST category |

See [Outbound traffic from CMS](outbound-publishing-api.md).

---

## Authenticated JSON from the browser

Axios is configured with CSRF in `resources/js/bootstrap.js`:

```javascript
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

Article form inline create uses `data-create-url` on category and topic selects (`resources/js/article-form.js`).

---

## External clients

Not supported. Integrate by implementing receiver endpoints on your site, not by calling these CMS routes.
