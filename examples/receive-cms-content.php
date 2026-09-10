<?php

/**
 * CMS content receiver (single-file, pure PHP).
 *
 * Deploy on the receiving site and point the CMS site api_endpoint here, e.g.:
 *   https://yoursite.com/api/cms/receive-cms-content.php
 *
 * Webuzo + Apache layout (recommended on shared hosting):
 *   public_html/api/cms/receive-cms-content.php      <- this file
 *   public_html/api/cms/receive-cms-authors.php
 *   public_html/api/cms/receive-cms-categories.php
 *   public_html/api/cms/cms-receiver-common.php      <- shared MySQL config
 *   public_html/media/cms/articles/                 <- saved images (web-visible)
 *
 * Copy examples/webuzo-apache/*.htaccess into the matching folders.
 * Set the Webuzo config block below, then chmod 775 on media/cms and api/cms/storage.
 *
 * Expects JSON POST from the CMS with headers:
 *   Content-Type: application/json
 *   X-API-Key: <shared secret>
 *   Idempotency-Key: <article-uuid>-<site-id>   (optional but recommended)
 *
 * Images are decoded and stored locally. Published HTML never keeps CMS URLs
 * or remote data: URIs once processing succeeds.
 */

declare(strict_types=1);

// --- Configuration ----------------------------------------------------------

const CMS_API_KEY = 'replace-with-your-api-key';

/**
 * Webuzo / Apache (shared hosting) — use these instead of the defaults below:
 *
 * const CMS_STORAGE_ROOT = dirname(__DIR__, 2).'/media/cms';
 * const CMS_PUBLIC_MEDIA_BASE = '/media/cms/articles';
 * const CMS_DB_PATH = __DIR__.'/storage/cms.sqlite';
 */

/** Default layout: everything next to this script. */
const CMS_STORAGE_ROOT = __DIR__.'/storage';

/** Public URL path to CMS_STORAGE_ROOT/articles (Webuzo: /media/cms/articles). */
const CMS_PUBLIC_MEDIA_BASE = '/media/articles';

/** Max decoded image size (bytes). */
const CMS_MAX_IMAGE_BYTES = 8 * 1024 * 1024;

const CMS_DB_PATH = CMS_STORAGE_ROOT.'/cms.sqlite';

// --- Bootstrap ----------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
    if (isset($_GET['media'])) {
        serveMedia((string) $_GET['media']);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        respond(405, ['error' => 'Method not allowed. Use POST.']);
    }

    receiveContent();
    exit;
}

// --- HTTP entrypoint ----------------------------------------------------------

function receiveContent(): void
{
    $apiKey = requestHeader('X-API-Key');

    if ($apiKey === null || ! hash_equals(CMS_API_KEY, $apiKey)) {
        respond(401, ['error' => 'Invalid API key.']);
    }

    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        respond(400, ['error' => 'Empty request body.']);
    }

    try {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        respond(400, ['error' => 'Invalid JSON body.']);
    }

    foreach (['uuid', 'slug', 'type', 'layout', 'title', 'content'] as $field) {
        if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
            respond(422, ['error' => "Missing or invalid field: {$field}."]);
        }
    }

    $uuid = $payload['uuid'];
    $articleDir = articleDirectory($uuid);
    ensureDirectory($articleDir);
    ensureDirectory($articleDir.'/content');

    $featuredImageUrl = processFeaturedImage($payload, $articleDir, $uuid);
    $content = localizeContentImages((string) $payload['content'], $articleDir, $uuid);

    $record = [
        'uuid' => $uuid,
        'slug' => $payload['slug'],
        'type' => $payload['type'],
        'layout' => $payload['layout'],
        'title' => $payload['title'],
        'excerpt' => stringOrNull($payload['excerpt'] ?? null),
        'keywords' => stringOrNull($payload['keywords'] ?? null),
        'content' => $content,
        'content_format' => stringOrNull($payload['content_format'] ?? null) ?? 'html',
        'reading_time_minutes' => intOrNull($payload['reading_time_minutes'] ?? null),
        'site_category_id' => intOrNull($payload['site_category_id'] ?? null),
        'author_id' => intOrNull($payload['author_id'] ?? null),
        'site_id' => intOrNull($payload['site_id'] ?? null),
        'site_name' => stringOrNull($payload['site_name'] ?? null),
        'published_at' => stringOrNull($payload['published_at'] ?? null),
        'featured_image_url' => $featuredImageUrl,
        'idempotency_key' => requestHeader('Idempotency-Key'),
        'received_at' => gmdate('Y-m-d H:i:s'),
    ];

    saveArticle($record);

    respond(201, [
        'status' => 'accepted',
        'uuid' => $uuid,
        'slug' => $record['slug'],
        'featured_image_url' => $featuredImageUrl,
        'content_format' => $record['content_format'],
    ]);
}

// --- Image handling -----------------------------------------------------------

function processFeaturedImage(array $payload, string $articleDir, string $uuid): ?string
{
    if (isset($payload['featured_image_base64'], $payload['featured_image_mime'])) {
        $binary = decodeBase64((string) $payload['featured_image_base64']);
        $mime = normalizeMime((string) $payload['featured_image_mime']);
        $filename = safeFilename(
            (string) ($payload['featured_image_filename'] ?? 'featured'),
            $mime,
        );

        $relativePath = "{$uuid}/{$filename}";
        $absolutePath = CMS_STORAGE_ROOT.'/articles/'.$relativePath;

        writeImage($absolutePath, $binary, $mime);

        return publicMediaUrl($relativePath);
    }

    if (isset($payload['featured_image_url']) && is_string($payload['featured_image_url']) && $payload['featured_image_url'] !== '') {
        respond(422, [
            'error' => 'featured_image_url without embedded file is not accepted. Re-upload the image in the CMS so it can be sent as featured_image_base64.',
            'featured_image_url' => $payload['featured_image_url'],
        ]);
    }

    return null;
}

function localizeContentImages(string $html, string $articleDir, string $uuid): string
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

        $mime = normalizeMime($matches[1]);
        $binary = decodeBase64($matches[2]);
        $hash = substr(hash('sha256', $binary), 0, 16);
        $filename = $hash.'.'.extensionForMime($mime);
        $relativePath = "{$uuid}/content/{$filename}";
        $absolutePath = CMS_STORAGE_ROOT.'/articles/'.$relativePath;

        writeImage($absolutePath, $binary, $mime);
        $image->setAttribute('src', publicMediaUrl($relativePath));
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

function writeImage(string $absolutePath, string $binary, string $mime): void
{
    if (strlen($binary) > CMS_MAX_IMAGE_BYTES) {
        respond(413, ['error' => 'Image exceeds maximum allowed size.']);
    }

    if (! str_starts_with($mime, 'image/')) {
        respond(422, ['error' => 'Only image uploads are supported.']);
    }

    ensureDirectory(dirname($absolutePath));

    if (file_put_contents($absolutePath, $binary) === false) {
        respond(500, ['error' => 'Failed to write image to storage.']);
    }
}

function decodeBase64(string $value): string
{
    $normalized = preg_replace('/\s+/', '', $value) ?? $value;
    $binary = base64_decode($normalized, true);

    if ($binary === false) {
        respond(422, ['error' => 'Invalid base64 image data.']);
    }

    return $binary;
}

function serveMedia(string $token): void
{
    $relativePath = str_replace(['..', '\\'], '', rawurldecode($token));
    $absolutePath = CMS_STORAGE_ROOT.'/articles/'.$relativePath;

    if (! is_file($absolutePath)) {
        respond(404, ['error' => 'Media not found.']);
    }

    $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';

    header('Content-Type: '.$mime);
    header('Content-Length: '.(string) filesize($absolutePath));
    readfile($absolutePath);
    exit;
}

// --- Persistence --------------------------------------------------------------

function saveArticle(array $record): void
{
    ensureDirectory(CMS_STORAGE_ROOT);

    $pdo = new PDO('sqlite:'.CMS_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS articles (
            uuid TEXT PRIMARY KEY,
            slug TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL,
            layout TEXT NOT NULL,
            title TEXT NOT NULL,
            excerpt TEXT,
            keywords TEXT,
            content TEXT NOT NULL,
            content_format TEXT NOT NULL,
            reading_time_minutes INTEGER,
            site_category_id INTEGER,
            author_id INTEGER,
            site_id INTEGER,
            site_name TEXT,
            published_at TEXT,
            featured_image_url TEXT,
            idempotency_key TEXT,
            received_at TEXT NOT NULL
        )
    SQL);

    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO articles (
            uuid, slug, type, layout, title, excerpt, keywords, content, content_format,
            reading_time_minutes, site_category_id, author_id, site_id, site_name,
            published_at, featured_image_url, idempotency_key, received_at
        ) VALUES (
            :uuid, :slug, :type, :layout, :title, :excerpt, :keywords, :content, :content_format,
            :reading_time_minutes, :site_category_id, :author_id, :site_id, :site_name,
            :published_at, :featured_image_url, :idempotency_key, :received_at
        )
        ON CONFLICT(uuid) DO UPDATE SET
            slug = excluded.slug,
            type = excluded.type,
            layout = excluded.layout,
            title = excluded.title,
            excerpt = excluded.excerpt,
            keywords = excluded.keywords,
            content = excluded.content,
            content_format = excluded.content_format,
            reading_time_minutes = excluded.reading_time_minutes,
            site_category_id = excluded.site_category_id,
            author_id = excluded.author_id,
            site_id = excluded.site_id,
            site_name = excluded.site_name,
            published_at = excluded.published_at,
            featured_image_url = excluded.featured_image_url,
            idempotency_key = excluded.idempotency_key,
            received_at = excluded.received_at
    SQL);

    $statement->execute($record);
}

// --- Helpers ------------------------------------------------------------------

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function requestHeader(string $name): ?string
{
    $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $name));

    if (! isset($_SERVER[$serverKey]) || ! is_string($_SERVER[$serverKey])) {
        return null;
    }

    $value = trim($_SERVER[$serverKey]);

    return $value === '' ? null : $value;
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (! mkdir($path, 0775, true) && ! is_dir($path)) {
        respond(500, ['error' => 'Failed to create storage directory.']);
    }
}

function articleDirectory(string $uuid): string
{
    if (! preg_match('/^[a-f0-9-]{36}$/i', $uuid)) {
        respond(422, ['error' => 'Invalid article uuid.']);
    }

    return CMS_STORAGE_ROOT.'/articles/'.$uuid;
}

function publicMediaUrl(string $relativePath): string
{
    if (str_ends_with(CMS_PUBLIC_MEDIA_BASE, '=')) {
        return CMS_PUBLIC_MEDIA_BASE.rawurlencode($relativePath);
    }

    return CMS_PUBLIC_MEDIA_BASE.'/'.$relativePath;
}

function safeFilename(string $filename, string $mime): string
{
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $basename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $basename) ?? 'featured';
    $basename = trim($basename, '-_');

    if ($basename === '') {
        $basename = 'featured';
    }

    return $basename.'.'.extensionForMime($mime);
}

function extensionForMime(string $mime): string
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

function normalizeMime(string $mime): string
{
    return strtolower(trim(explode(';', $mime)[0]));
}

function stringOrNull(mixed $value): ?string
{
    if (! is_string($value)) {
        return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
}

function intOrNull(mixed $value): ?int
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
