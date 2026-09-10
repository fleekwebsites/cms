# CMS internal API

JSON endpoints consumed by the authenticated CMS web UI. All routes require a valid **session** and (for `POST`) a **CSRF token**.

Base URL: your CMS `APP_URL` (e.g. `http://localhost:8000`).

There is no `/api` prefix. Routes are defined in `routes/web.php`.

---

## POST `/articles/images`

Upload an image from the Quill rich-text editor.

### Authorization

User must be allowed to **create** articles (`ArticlePolicy@create`).

### Request

```http
POST /articles/images
Content-Type: multipart/form-data
X-CSRF-TOKEN: {token}
Cookie: laravel_session={session}
```

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `file` | file | Yes | Image; `jpg`, `jpeg`, `png`, `gif`, `webp`; max **5120 KB** (5 MB) |

### Success response `200`

```json
{
  "location": "https://cms.example.com/storage/article-images/xYz123.png"
}
```

| Field | Description |
|-------|-------------|
| `location` | Public URL of the stored file under `storage/app/public/article-images/` |

The editor inserts this URL into article HTML. On publish, locally stored images in content are embedded as Base64 data URIs in the outbound payload.

### Error responses

| Status | Cause |
|--------|-------|
| `401` | Not logged in |
| `403` | User cannot create articles |
| `419` | Missing or invalid CSRF token |
| `422` | Validation failed (wrong type, too large, etc.) |

**422 example:**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["The file field must be an image."]
  }
}
```

### Usage in the app

Called from `resources/js/editor.js` via Axios when the user inserts an image in the toolbar. The upload URL is provided as `data-upload-url` on the hidden `#content` textarea.

---

## GET `/sites/{site}/categories`

List categories for a site (populates the article form category dropdown).

### Authorization

- **Admin:** any site.
- **Writer:** only if the site is **active**.

### Request

```http
GET /sites/{site}/categories
Accept: application/json
Cookie: laravel_session={session}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `site` | integer | Site ID (route model binding) |

### Success response `200`

```json
[
  { "id": 12, "name": "NP Programs" },
  { "id": 15, "name": "Study Tips" }
]
```

Sorted by `name`, then `id`.

### Error responses

| Status | Cause |
|--------|-------|
| `401` | Not logged in |
| `403` | Not allowed to view categories for this site |
| `404` | Site not found |

### Usage in the app

Loaded by `resources/js/article-form.js` when the user selects a target site. Expects `data-categories-url` on the category `<select>`.

---

## GET `/sites/{site}/authors`

List authors for a site (populates the article form author dropdown).

### Authorization

Same as categories: admin for any site; writer for active sites only.

### Request

```http
GET /sites/{site}/authors
Accept: application/json
Cookie: laravel_session={session}
```

### Success response `200`

```json
[
  {
    "id": 7,
    "name": "Elena Marsh · DNP, FNP-BC"
  }
]
```

| Field | Description |
|-------|-------------|
| `id` | Author ID (sent to remote site as `author_id` on publish) |
| `name` | Display line: `{name}` or `{name} · {credentials}` if credentials are set |

Sorted by author `name`, then `id`.

### Error responses

Same as categories (`401`, `403`, `404`).

### Usage in the app

Loaded by `resources/js/article-form.js` when the user selects a target site.

---

## Related non-JSON routes

These trigger outbound API calls but return HTML redirects, not JSON:

| Route | Method | Description |
|-------|--------|-------------|
| `/articles/{article}/publications` | `POST` | Publish article to its assigned site |
| `/authors` | `POST` | Create author; syncs to remote site |
| `/authors/{author}` | `PUT/PATCH` | Update author; syncs to remote site |
| `/sites/{site}/categories` | `POST` | Create category; syncs to remote site |

See [Outbound publishing API](outbound-publishing-api.md) for remote payloads.

---

## Making authenticated JSON requests

### From the browser (Axios)

The CMS sets up Axios with CSRF in `resources/js/bootstrap.js`:

```javascript
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// CSRF token read from <meta name="csrf-token">
```

### From tests

```php
$this->actingAs($user)->getJson(route('sites.authors.index', $site));
```

### From external clients

Not officially supported. The CMS is designed as a server-rendered application. Integrate with remote sites via the [receiver API](remote-site-receiver-api.md), not by calling CMS routes.
