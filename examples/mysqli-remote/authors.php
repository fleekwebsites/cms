<?php

/**
 * CMS authors API (mysqli) — matches CMS gateway URLs.
 *
 * CMS site api_endpoint:
 *   https://yourdomain.com/api/content
 *
 * Remote URLs (via .htaccess rewrite):
 *   GET    /api/authors/           → list JSON array
 *   GET    /api/authors/{id}      → single author object
 *   POST   /api/authors/          → upsert { id, name, credentials?, bio? }
 *                                     id = CMS client id on create; remote id on update
 *                                     response includes { id, client_id }
 *   DELETE /api/authors/{id}      → remove
 *
 * Headers: X-API-Key (required), Idempotency-Key (POST/DELETE)
 */

declare(strict_types=1);

require_once __DIR__.'/../../../conn/config.php';

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

function cms_requested_author_id(): ?int
{
    if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
        return (int) $_GET['id'];
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

    if (preg_match('#/authors/([0-9]+)/?$#i', $uri, $matches) === 1) {
        return (int) $matches[1];
    }

    $pathInfo = trim($_SERVER['PATH_INFO'] ?? '', '/');

    if ($pathInfo !== '' && ctype_digit($pathInfo)) {
        return (int) $pathInfo;
    }

    return null;
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

function cms_required_positive_int(array $payload, string $field): int
{
    $value = cms_int_or_null($payload[$field] ?? null);

    if ($value === null || $value < 1) {
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

function cms_ensure_author_table(mysqli $db): void
{
    $result = $db->query("SHOW TABLES LIKE 'cms_authors'");

    if ($result === false) {
        cms_respond(500, ['error' => 'Database query failed.']);

        return;
    }

    if ($result->num_rows === 0) {
        $db->query('CREATE TABLE cms_authors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            credentials VARCHAR(255) NULL,
            bio TEXT NULL,
            idempotency_key VARCHAR(255) NULL,
            received_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY cms_authors_client_id_unique (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        return;
    }

    $column = $db->query("SHOW COLUMNS FROM cms_authors LIKE 'client_id'");

    if ($column !== false && $column->num_rows === 0) {
        $db->query('ALTER TABLE cms_authors ADD COLUMN client_id INT UNSIGNED NULL AFTER id');
        $db->query('ALTER TABLE cms_authors ADD UNIQUE KEY cms_authors_client_id_unique (client_id)');
    }

    $idColumn = $db->query("SHOW COLUMNS FROM cms_authors LIKE 'id'");

    if ($idColumn !== false && ($idColumn->fetch_assoc()['Extra'] ?? '') !== 'auto_increment') {
        $db->query('ALTER TABLE cms_authors MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
    }
}

function cms_find_author_by_request_id(mysqli $db, int $requestId): ?array
{
    $stmt = $db->prepare('SELECT * FROM cms_authors WHERE id = ? OR client_id = ? LIMIT 1');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('ii', $requestId, $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result?->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

/**
 * @param  array<string, mixed>  $row
 * @return array<string, mixed>
 */
function cms_author_array(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'credentials' => $row['credentials'],
        'bio' => $row['bio'],
    ];
}

function cms_list_authors(mysqli $db): void
{
    $result = $db->query('SELECT id, name, credentials, bio FROM cms_authors ORDER BY name, id');

    if ($result === false) {
        cms_respond(500, ['error' => 'Database query failed.']);
    }

    $authors = [];

    while ($row = $result->fetch_assoc()) {
        $authors[] = cms_author_array($row);
    }

    cms_respond(200, $authors);
}

function cms_show_author(mysqli $db, int $id): void
{
    $stmt = $db->prepare('SELECT id, name, credentials, bio FROM cms_authors WHERE id = ? LIMIT 1');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result?->fetch_assoc();
    $stmt->close();

    if (! is_array($row)) {
        cms_respond(404, ['error' => 'Author not found.']);
    }

    cms_respond(200, cms_author_array($row));
}

function cms_upsert_author(mysqli $db): void
{
    /** @var array<string, mixed> $payload */
    $payload = cms_json_payload();

    $requestId = cms_required_positive_int($payload, 'id');
    $name = cms_required_string($payload, 'name');
    $credentials = cms_string_or_null($payload['credentials'] ?? null);
    $bio = cms_string_or_null($payload['bio'] ?? null);
    $receivedAt = cms_received_at();
    $idempotencyKey = cms_request_header('Idempotency-Key');
    $existing = cms_find_author_by_request_id($db, $requestId);

    if ($existing !== null) {
        $remoteId = (int) $existing['id'];
        $clientId = isset($existing['client_id']) ? (int) $existing['client_id'] : $requestId;

        $stmt = $db->prepare('UPDATE cms_authors SET
            name = ?,
            credentials = ?,
            bio = ?,
            idempotency_key = ?,
            received_at = ?,
            updated_at = ?
            WHERE id = ?');

        if ($stmt === false) {
            cms_respond(500, ['error' => 'Database preparation failed: '.$db->error]);
        }

        $stmt->bind_param(
            'ssssssi',
            $name,
            $credentials,
            $bio,
            $idempotencyKey,
            $receivedAt,
            $receivedAt,
            $remoteId,
        );
    } else {
        $clientId = $requestId;

        $stmt = $db->prepare('INSERT INTO cms_authors (
            client_id, name, credentials, bio, idempotency_key, received_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?
        )');

        if ($stmt === false) {
            cms_respond(500, ['error' => 'Database preparation failed: '.$db->error]);
        }

        $stmt->bind_param(
            'issssss',
            $clientId,
            $name,
            $credentials,
            $bio,
            $idempotencyKey,
            $receivedAt,
            $receivedAt,
        );
    }

    if (! $stmt->execute()) {
        cms_respond(500, ['error' => 'Database execution failed: '.$stmt->error]);
    }

    if ($existing === null) {
        $remoteId = (int) $db->insert_id;
    }

    $stmt->close();

    cms_respond(201, [
        'status' => 'accepted',
        'id' => $remoteId,
        'client_id' => $clientId,
        'name' => $name,
        'credentials' => $credentials,
    ]);
}

function cms_delete_author(mysqli $db, int $id): void
{
    $stmt = $db->prepare('DELETE FROM cms_authors WHERE id = ?');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('i', $id);

    if (! $stmt->execute()) {
        cms_respond(500, ['error' => 'Database execution failed: '.$stmt->error]);
    }

    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (! $deleted) {
        cms_respond(404, ['error' => 'Author not found.']);
    }

    cms_respond(200, [
        'status' => 'deleted',
        'id' => $id,
    ]);
}

// --- Main ---------------------------------------------------------------------

cms_authenticate();

$db = cms_db();
cms_ensure_author_table($db);

$method = cms_request_method();
$authorId = cms_requested_author_id();

match (true) {
    $method === 'GET' && $authorId === null => cms_list_authors($db),
    $method === 'GET' && $authorId !== null => cms_show_author($db, $authorId),
    $method === 'POST' && $authorId === null => cms_upsert_author($db),
    $method === 'DELETE' && $authorId !== null => cms_delete_author($db, $authorId),
    default => cms_respond(405, ['error' => 'Method not allowed for this URL.']),
};
