<?php

/**
 * CMS articles API (mysqli) — matches CMS gateway URLs.
 *
 * CMS site api_endpoint:
 *   https://yourdomain.com/api/content
 *
 * Remote URLs (via .htaccess rewrite):
 *   GET    /api/content/              → list JSON array (?type=&status=)
 *   GET    /api/content/{uuid}        → single article object
 *   POST   /api/content/              → upsert article JSON (+ embedded images)
 *   DELETE /api/content/{uuid}        → remove article + stored media
 *
 * Legacy /api/articles/ paths are also supported by .htaccess.
 *
 * Media files saved under CMS_STORAGE_ROOT; served at CMS_PUBLIC_MEDIA_BASE
 * or via GET articles.php?media={uuid}/filename
 *
 * Headers: X-API-Key (required), Idempotency-Key (POST/DELETE)
 *
 * Adjust paths below for your server layout.
 */

declare(strict_types=1);

require_once __DIR__.'/../../conn/config.php';

/** Directory where article images are stored (web server must be able to write here). */
const CMS_STORAGE_ROOT = __DIR__.'/../images';

/** Public URL prefix for stored images, e.g. /blogs/images */
const CMS_PUBLIC_MEDIA_BASE = '/blogs/images';

/** Max decoded image size (bytes). */
const CMS_MAX_IMAGE_BYTES = 8 * 1024 * 1024;

// --- HTTP helpers -------------------------------------------------------------

function cms_authenticate(): void
{
    $apiKey = cms_request_header('X-API-Key');

    if ($apiKey === null || ! hash_equals(CMS_API_KEY, $apiKey)) {
        cms_respond(401, ['error' => 'Invalid API key.']);
    }
}

function cms_respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function cms_request_header(string $name): ?string
{
    $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $name));

    if (! isset($_SERVER[$serverKey]) || ! is_string($_SERVER[$serverKey])) {
        return null;
    }

    $value = trim($_SERVER[$serverKey]);

    return $value === '' ? null : $value;
}

function cms_request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * @return array<string, mixed>
 */
function cms_json_payload(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        cms_respond(400, ['error' => 'Empty request body.']);
    }

    try {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        cms_respond(400, ['error' => 'Invalid JSON body.']);
    }

    return $payload;
}

function cms_received_at(): string
{
    return gmdate('Y-m-d H:i:s');
}

function cms_requested_article_uuid(): ?string
{
    if (isset($_GET['uuid']) && is_string($_GET['uuid']) && cms_is_valid_uuid($_GET['uuid'])) {
        return strtolower($_GET['uuid']);
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

    if (preg_match('#/(?:articles|content)/([a-f0-9\-]{36})/?$#i', $uri, $matches) === 1) {
        return strtolower($matches[1]);
    }

    $pathInfo = trim($_SERVER['PATH_INFO'] ?? '', '/');

    if ($pathInfo !== '' && cms_is_valid_uuid($pathInfo)) {
        return strtolower($pathInfo);
    }

    return null;
}

function cms_is_valid_uuid(string $uuid): bool
{
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid) === 1;
}

function cms_query_string(string $key): ?string
{
    if (! isset($_GET[$key]) || ! is_string($_GET[$key])) {
        return null;
    }

    $value = trim($_GET[$key]);

    return $value === '' ? null : $value;
}

// --- Validation ---------------------------------------------------------------

function cms_required_string(array $payload, string $field): string
{
    if (! isset($payload[$field]) || ! is_string($payload[$field])) {
        cms_respond(422, ['error' => "Missing or invalid field: {$field}."]);
    }

    $value = trim($payload[$field]);

    if ($value === '') {
        cms_respond(422, ['error' => "Missing or invalid field: {$field}."]);
    }

    return $value;
}

function cms_string_or_null(mixed $value): ?string
{
    if (! is_string($value)) {
        return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
}

function cms_int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value)) {
        return (int) $value;
    }

    return null;
}

// --- Database -----------------------------------------------------------------

function cms_db(): mysqli
{
    global $config;

    if (! $config instanceof mysqli) {
        cms_respond(500, ['error' => 'Database configuration error.']);
    }

    return $config;
}

function cms_ensure_article_table(mysqli $db): void
{
    $result = $db->query("SHOW TABLES LIKE 'articles'");

    if ($result === false) {
        cms_respond(500, ['error' => 'Database query failed.']);

        return;
    }

    if ($result->num_rows === 0) {
        $db->query("CREATE TABLE articles (
            uuid CHAR(36) NOT NULL PRIMARY KEY,
            slug VARCHAR(255) NOT NULL,
            type VARCHAR(32) NOT NULL,
            layout VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            title VARCHAR(255) NOT NULL,
            excerpt TEXT NULL,
            keywords TEXT NULL,
            content MEDIUMTEXT NOT NULL,
            content_format VARCHAR(32) NOT NULL DEFAULT 'html',
            reading_time_minutes INT UNSIGNED NULL,
            site_category_id INT UNSIGNED NULL,
            topic_id INT UNSIGNED NULL,
            author_id INT UNSIGNED NULL,
            site_id INT UNSIGNED NULL,
            site_name VARCHAR(255) NULL,
            editor_user_id INT UNSIGNED NULL,
            editor_name VARCHAR(255) NULL,
            published_at DATETIME NULL,
            featured_image_url VARCHAR(512) NULL,
            idempotency_key VARCHAR(255) NULL,
            received_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY articles_slug_unique (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return;
    }

    $columns = [
        'status' => "ALTER TABLE articles ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'draft' AFTER layout",
        'topic_id' => 'ALTER TABLE articles ADD COLUMN topic_id INT UNSIGNED NULL AFTER site_category_id',
        'editor_user_id' => 'ALTER TABLE articles ADD COLUMN editor_user_id INT UNSIGNED NULL AFTER site_name',
        'editor_name' => 'ALTER TABLE articles ADD COLUMN editor_name VARCHAR(255) NULL AFTER editor_user_id',
        'updated_at' => 'ALTER TABLE articles ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER received_at',
    ];

    foreach ($columns as $name => $sql) {
        $column = $db->query("SHOW COLUMNS FROM articles LIKE '{$name}'");

        if ($column !== false && $column->num_rows === 0) {
            $db->query($sql);
        }
    }
}

/**
 * @param  array<string, mixed>  $row
 * @return array<string, mixed>
 */
function cms_article_array(array $row): array
{
    $article = [
        'uuid' => (string) $row['uuid'],
        'slug' => (string) $row['slug'],
        'type' => (string) $row['type'],
        'layout' => (string) $row['layout'],
        'status' => (string) ($row['status'] ?? 'draft'),
        'title' => (string) $row['title'],
        'content' => (string) $row['content'],
        'content_format' => (string) ($row['content_format'] ?? 'html'),
        'received_at' => (string) $row['received_at'],
        'updated_at' => (string) ($row['updated_at'] ?? $row['received_at']),
    ];

    foreach ([
        'excerpt',
        'keywords',
        'site_name',
        'editor_name',
        'published_at',
        'featured_image_url',
    ] as $field) {
        if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
            $article[$field] = (string) $row[$field];
        }
    }

    foreach ([
        'reading_time_minutes',
        'site_category_id',
        'topic_id',
        'author_id',
        'site_id',
        'editor_user_id',
    ] as $field) {
        if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
            $article[$field] = (int) $row[$field];
        }
    }

    return $article;
}

function cms_find_article(mysqli $db, string $uuid): ?array
{
    $stmt = $db->prepare('SELECT * FROM articles WHERE uuid = ? LIMIT 1');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result?->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

function cms_list_articles(mysqli $db): void
{
    $type = cms_query_string('type');
    $status = cms_query_string('status');

    $sql = 'SELECT * FROM articles WHERE 1=1';
    $types = '';
    $params = [];

    if ($type !== null) {
        $sql .= ' AND type = ?';
        $types .= 's';
        $params[] = $type;
    }

    if ($status !== null) {
        $sql .= ' AND status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $sql .= ' ORDER BY updated_at DESC, received_at DESC';

    $stmt = $db->prepare($sql);

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $articles = [];

    while ($row = $result->fetch_assoc()) {
        $articles[] = cms_article_array($row);
    }

    $stmt->close();

    cms_respond(200, $articles);
}

function cms_show_article(mysqli $db, string $uuid): void
{
    $row = cms_find_article($db, $uuid);

    if ($row === null) {
        cms_respond(404, ['error' => 'Article not found.']);
    }

    cms_respond(200, cms_article_array($row));
}

function cms_upsert_article(mysqli $db): void
{
    /** @var array<string, mixed> $payload */
    $payload = cms_json_payload();

    foreach (['uuid', 'slug', 'type', 'layout', 'title', 'content'] as $field) {
        if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
            cms_respond(422, ['error' => "Missing or invalid field: {$field}."]);
        }
    }

    $uuid = strtolower(trim($payload['uuid']));

    if (! cms_is_valid_uuid($uuid)) {
        cms_respond(422, ['error' => 'Invalid article uuid.']);
    }

    $existing = cms_find_article($db, $uuid);
    $articleDir = cms_article_directory($uuid);
    cms_ensure_directory($articleDir);
    cms_ensure_directory($articleDir.'/content');

    $featuredImageUrl = cms_process_featured_image(
        $payload,
        $uuid,
        cms_string_or_null($existing['featured_image_url'] ?? null),
    );
    $content = cms_localize_content_images((string) $payload['content'], $uuid);

    $publishedAt = cms_string_or_null($payload['published_at'] ?? null);
    $status = cms_string_or_null($payload['status'] ?? null)
        ?? ($publishedAt !== null ? 'published' : 'draft');

    $receivedAt = cms_received_at();
    $record = [
        'uuid' => $uuid,
        'slug' => trim($payload['slug']),
        'type' => trim($payload['type']),
        'layout' => trim($payload['layout']),
        'status' => $status,
        'title' => trim($payload['title']),
        'excerpt' => cms_string_or_null($payload['excerpt'] ?? null),
        'keywords' => cms_string_or_null($payload['keywords'] ?? null),
        'content' => $content,
        'content_format' => cms_string_or_null($payload['content_format'] ?? null) ?? 'html',
        'reading_time_minutes' => cms_int_or_null($payload['reading_time_minutes'] ?? null),
        'site_category_id' => cms_int_or_null($payload['site_category_id'] ?? null),
        'topic_id' => cms_int_or_null($payload['topic_id'] ?? null),
        'author_id' => cms_int_or_null($payload['author_id'] ?? null),
        'site_id' => cms_int_or_null($payload['site_id'] ?? null),
        'site_name' => cms_string_or_null($payload['site_name'] ?? null),
        'editor_user_id' => cms_int_or_null($payload['editor_user_id'] ?? null),
        'editor_name' => cms_string_or_null($payload['editor_name'] ?? null),
        'published_at' => $publishedAt,
        'featured_image_url' => $featuredImageUrl,
        'idempotency_key' => cms_request_header('Idempotency-Key'),
        'received_at' => $receivedAt,
        'updated_at' => $receivedAt,
    ];

    $stmt = $db->prepare('INSERT INTO articles (
        uuid, slug, type, layout, status, title, excerpt, keywords, content, content_format,
        reading_time_minutes, site_category_id, topic_id, author_id, site_id, site_name,
        editor_user_id, editor_name, published_at, featured_image_url, idempotency_key,
        received_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    ) ON DUPLICATE KEY UPDATE
        slug = VALUES(slug),
        type = VALUES(type),
        layout = VALUES(layout),
        status = VALUES(status),
        title = VALUES(title),
        excerpt = VALUES(excerpt),
        keywords = VALUES(keywords),
        content = VALUES(content),
        content_format = VALUES(content_format),
        reading_time_minutes = VALUES(reading_time_minutes),
        site_category_id = VALUES(site_category_id),
        topic_id = VALUES(topic_id),
        author_id = VALUES(author_id),
        site_id = VALUES(site_id),
        site_name = VALUES(site_name),
        editor_user_id = VALUES(editor_user_id),
        editor_name = VALUES(editor_name),
        published_at = VALUES(published_at),
        featured_image_url = VALUES(featured_image_url),
        idempotency_key = VALUES(idempotency_key),
        received_at = VALUES(received_at),
        updated_at = VALUES(updated_at)');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed: '.$db->error]);
    }

    $stmt->bind_param(
        'ssssssssssiiiiissssssss',
        $record['uuid'],
        $record['slug'],
        $record['type'],
        $record['layout'],
        $record['status'],
        $record['title'],
        $record['excerpt'],
        $record['keywords'],
        $record['content'],
        $record['content_format'],
        $record['reading_time_minutes'],
        $record['site_category_id'],
        $record['topic_id'],
        $record['author_id'],
        $record['site_id'],
        $record['site_name'],
        $record['editor_user_id'],
        $record['editor_name'],
        $record['published_at'],
        $record['featured_image_url'],
        $record['idempotency_key'],
        $record['received_at'],
        $record['updated_at'],
    );

    if (! $stmt->execute()) {
        cms_respond(500, ['error' => 'Database execution failed: '.$stmt->error]);
    }

    $stmt->close();

    cms_respond(201, [
        'status' => 'accepted',
        'uuid' => $uuid,
        'slug' => $record['slug'],
        'featured_image_url' => $featuredImageUrl,
        'content_format' => $record['content_format'],
    ]);
}

function cms_delete_article(mysqli $db, string $uuid): void
{
    $stmt = $db->prepare('DELETE FROM articles WHERE uuid = ?');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('s', $uuid);

    if (! $stmt->execute()) {
        cms_respond(500, ['error' => 'Database execution failed: '.$stmt->error]);
    }

    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (! $deleted) {
        cms_respond(404, ['error' => 'Article not found.']);
    }

    cms_delete_directory(cms_article_directory($uuid));

    cms_respond(200, [
        'status' => 'deleted',
        'uuid' => $uuid,
    ]);
}

// --- Image handling -----------------------------------------------------------

/**
 * @param  array<string, mixed>  $payload
 */
function cms_process_featured_image(array $payload, string $uuid, ?string $existingUrl = null): ?string
{
    if (isset($payload['featured_image_base64'], $payload['featured_image_mime'])) {
        $binary = cms_decode_base64((string) $payload['featured_image_base64']);
        $mime = cms_normalize_mime((string) $payload['featured_image_mime']);
        $filename = cms_safe_filename(
            (string) ($payload['featured_image_filename'] ?? 'featured'),
            $mime,
        );

        $relativePath = "{$uuid}/{$filename}";
        $absolutePath = CMS_STORAGE_ROOT.'/'.$relativePath;

        cms_write_image($absolutePath, $binary, $mime);

        return cms_public_media_url($relativePath);
    }

    $url = cms_string_or_null($payload['featured_image_url'] ?? null);

    if ($url !== null) {
        if (cms_is_local_media_url($url)) {
            return $url;
        }

        cms_respond(422, [
            'error' => 'featured_image_url without embedded file is not accepted. Re-upload the image in the CMS so it can be sent as featured_image_base64.',
            'featured_image_url' => $url,
        ]);
    }

    return $existingUrl;
}

function cms_localize_content_images(string $html, string $uuid): string
{
    if ($html === '' || stripos($html, '<img') === false) {
        return $html;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);

    $document->loadHTML(
        '<?xml encoding="UTF-8"><div id="content-root">'.$html.'</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $images = $document->getElementsByTagName('img');

    for ($index = $images->length - 1; $index >= 0; $index--) {
        $image = $images->item($index);

        if (! $image instanceof DOMElement) {
            continue;
        }

        $src = $image->getAttribute('src');

        if ($src === '' || ! str_starts_with($src, 'data:')) {
            continue;
        }

        if (! preg_match('#^data:([^;]+);base64,(.+)$#', $src, $matches)) {
            continue;
        }

        $mime = cms_normalize_mime($matches[1]);
        $binary = cms_decode_base64($matches[2]);
        $hash = substr(hash('sha256', $binary), 0, 16);
        $filename = $hash.'.'.cms_extension_for_mime($mime);
        $relativePath = "{$uuid}/content/{$filename}";
        $absolutePath = CMS_STORAGE_ROOT.'/'.$relativePath;

        cms_write_image($absolutePath, $binary, $mime);
        $image->setAttribute('src', cms_public_media_url($relativePath));
    }

    $root = $document->getElementById('content-root');

    if (! $root instanceof DOMElement) {
        return $html;
    }

    $output = '';

    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }

    return $output;
}

function cms_write_image(string $absolutePath, string $binary, string $mime): void
{
    if (strlen($binary) > CMS_MAX_IMAGE_BYTES) {
        cms_respond(413, ['error' => 'Image exceeds maximum allowed size.']);
    }

    if (! str_starts_with($mime, 'image/')) {
        cms_respond(422, ['error' => 'Only image uploads are supported.']);
    }

    cms_ensure_directory(dirname($absolutePath));

    if (file_put_contents($absolutePath, $binary) === false) {
        cms_respond(500, ['error' => 'Failed to write image to storage.']);
    }
}

function cms_decode_base64(string $value): string
{
    $normalized = preg_replace('/\s+/', '', $value) ?? $value;
    $binary = base64_decode($normalized, true);

    if ($binary === false) {
        cms_respond(422, ['error' => 'Invalid base64 image data.']);
    }

    return $binary;
}

function cms_serve_media(string $token): void
{
    $relativePath = str_replace(['..', '\\'], '', rawurldecode($token));
    $absolutePath = CMS_STORAGE_ROOT.'/'.$relativePath;

    if (! is_file($absolutePath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Media not found.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';

    header('Content-Type: '.$mime);
    header('Content-Length: '.(string) filesize($absolutePath));
    readfile($absolutePath);
    exit;
}

function cms_is_local_media_url(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH);

    if (! is_string($path) || $path === '') {
        return false;
    }

    $base = rtrim(CMS_PUBLIC_MEDIA_BASE, '/');

    return $path === $base || str_starts_with($path, $base.'/');
}

function cms_article_directory(string $uuid): string
{
    if (! cms_is_valid_uuid($uuid)) {
        cms_respond(422, ['error' => 'Invalid article uuid.']);
    }

    return CMS_STORAGE_ROOT.'/'.strtolower($uuid);
}

function cms_public_media_url(string $relativePath): string
{
    if (str_ends_with(CMS_PUBLIC_MEDIA_BASE, '=')) {
        return CMS_PUBLIC_MEDIA_BASE.rawurlencode($relativePath);
    }

    return CMS_PUBLIC_MEDIA_BASE.'/'.ltrim($relativePath, '/');
}

function cms_safe_filename(string $filename, string $mime): string
{
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $basename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $basename) ?? 'featured';
    $basename = trim($basename, '-_');

    if ($basename === '') {
        $basename = 'featured';
    }

    return $basename.'.'.cms_extension_for_mime($mime);
}

function cms_extension_for_mime(string $mime): string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/avif' => 'avif',
        default => 'bin',
    };
}

function cms_normalize_mime(string $mime): string
{
    return strtolower(trim(explode(';', $mime)[0]));
}

function cms_ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (! mkdir($path, 0775, true) && ! is_dir($path)) {
        cms_respond(500, ['error' => 'Failed to create storage directory.']);
    }
}

function cms_delete_directory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = scandir($path);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $path.'/'.$item;

        if (is_dir($fullPath)) {
            cms_delete_directory($fullPath);
        } else {
            unlink($fullPath);
        }
    }

    rmdir($path);
}

// --- Main ---------------------------------------------------------------------

if (PHP_SAPI !== 'cli' && isset($_GET['media'])) {
    cms_serve_media((string) $_GET['media']);
}

cms_authenticate();

$db = cms_db();
cms_ensure_article_table($db);

$method = cms_request_method();
$articleUuid = cms_requested_article_uuid();

match (true) {
    $method === 'GET' && $articleUuid === null => cms_list_articles($db),
    $method === 'GET' && $articleUuid !== null => cms_show_article($db, $articleUuid),
    $method === 'POST' && $articleUuid === null => cms_upsert_article($db),
    $method === 'DELETE' && $articleUuid !== null => cms_delete_article($db, $articleUuid),
    default => cms_respond(405, ['error' => 'Method not allowed for this URL.']),
};
