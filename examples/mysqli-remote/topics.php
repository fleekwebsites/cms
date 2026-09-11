<?php

/**
 * CMS topics API (mysqli) — matches CMS gateway URLs.
 *
 * CMS site api_endpoint:
 *   https://yourdomain.com/api/cms/content
 *
 * Remote URLs (via .htaccess rewrite):
 *   GET    /api/cms/topics/                    → list JSON array (?site_category_id=)
 *   GET    /api/cms/topics/{id}               → single topic object
 *   POST   /api/cms/topics/                   → upsert { id, site_category_id, name }
 *                                             id = CMS client id on create; remote id on update
 *                                             response includes { id, client_id, site_category_id }
 *   DELETE /api/cms/topics/{id}               → remove
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

function cms_requested_topic_id(): ?int
{
    if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
        return (int) $_GET['id'];
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

    if (preg_match('#/topics/([0-9]+)/?$#i', $uri, $matches) === 1) {
        return (int) $matches[1];
    }

    $pathInfo = trim($_SERVER['PATH_INFO'] ?? '', '/');

    if ($pathInfo !== '' && ctype_digit($pathInfo)) {
        return (int) $pathInfo;
    }

    return null;
}

function cms_requested_category_filter(): ?int
{
    return cms_int_or_null($_GET['site_category_id'] ?? null);
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

function cms_ensure_topic_table(mysqli $db): void
{
    $result = $db->query("SHOW TABLES LIKE 'cms_topics'");

    if ($result === false) {
        cms_respond(500, ['error' => 'Database query failed.']);

        return;
    }

    if ($result->num_rows === 0) {
        $db->query('CREATE TABLE cms_topics (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NULL,
            site_category_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            idempotency_key VARCHAR(255) NULL,
            received_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY cms_topics_client_id_unique (client_id),
            KEY cms_topics_site_category_id_index (site_category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        return;
    }

    $column = $db->query("SHOW COLUMNS FROM cms_topics LIKE 'client_id'");

    if ($column !== false && $column->num_rows === 0) {
        $db->query('ALTER TABLE cms_topics ADD COLUMN client_id INT UNSIGNED NULL AFTER id');
        $db->query('ALTER TABLE cms_topics ADD UNIQUE KEY cms_topics_client_id_unique (client_id)');
    }

    $idColumn = $db->query("SHOW COLUMNS FROM cms_topics LIKE 'id'");

    if ($idColumn !== false && ($idColumn->fetch_assoc()['Extra'] ?? '') !== 'auto_increment') {
        $db->query('ALTER TABLE cms_topics MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
    }
}

function cms_find_topic_by_request_id(mysqli $db, int $requestId): ?array
{
    $stmt = $db->prepare('SELECT * FROM cms_topics WHERE id = ? OR client_id = ? LIMIT 1');

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

function cms_resolve_category_id(mysqli $db, int $requestCategoryId): int
{
    $stmt = $db->prepare('SELECT id FROM cms_categories WHERE id = ? OR client_id = ? LIMIT 1');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('ii', $requestCategoryId, $requestCategoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result?->fetch_assoc();
    $stmt->close();

    if (! is_array($row)) {
        cms_respond(422, ['error' => 'The selected category does not exist on this site.']);
    }

    return (int) $row['id'];
}

/**
 * @param  array<string, mixed>  $row
 * @return array<string, mixed>
 */
function cms_topic_array(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'site_category_id' => (int) $row['site_category_id'],
    ];
}

function cms_list_topics(mysqli $db): void
{
    $categoryId = cms_requested_category_filter();

    if ($categoryId === null) {
        $result = $db->query('SELECT id, name, site_category_id FROM cms_topics ORDER BY name, id');
    } else {
        $resolvedCategoryId = cms_resolve_category_id($db, $categoryId);
        $stmt = $db->prepare('SELECT id, name, site_category_id FROM cms_topics WHERE site_category_id = ? ORDER BY name, id');

        if ($stmt === false) {
            cms_respond(500, ['error' => 'Database preparation failed.']);
        }

        $stmt->bind_param('i', $resolvedCategoryId);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    if ($result === false) {
        cms_respond(500, ['error' => 'Database query failed.']);
    }

    $topics = [];

    while ($row = $result->fetch_assoc()) {
        $topics[] = cms_topic_array($row);
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    cms_respond(200, $topics);
}

function cms_show_topic(mysqli $db, int $id): void
{
    $stmt = $db->prepare('SELECT id, name, site_category_id FROM cms_topics WHERE id = ? LIMIT 1');

    if ($stmt === false) {
        cms_respond(500, ['error' => 'Database preparation failed.']);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result?->fetch_assoc();
    $stmt->close();

    if (! is_array($row)) {
        cms_respond(404, ['error' => 'Topic not found.']);
    }

    cms_respond(200, cms_topic_array($row));
}

function cms_upsert_topic(mysqli $db): void
{
    /** @var array<string, mixed> $payload */
    $payload = cms_json_payload();

    $requestId = cms_required_positive_int($payload, 'id');
    $siteCategoryId = cms_resolve_category_id($db, cms_required_positive_int($payload, 'site_category_id'));
    $name = cms_required_string($payload, 'name');
    $receivedAt = cms_received_at();
    $idempotencyKey = cms_request_header('Idempotency-Key');
    $existing = cms_find_topic_by_request_id($db, $requestId);

    if ($existing !== null) {
        $remoteId = (int) $existing['id'];
        $clientId = isset($existing['client_id']) ? (int) $existing['client_id'] : $requestId;

        $stmt = $db->prepare('UPDATE cms_topics SET
            site_category_id = ?,
            name = ?,
            idempotency_key = ?,
            received_at = ?,
            updated_at = ?
            WHERE id = ?');

        if ($stmt === false) {
            cms_respond(500, ['error' => 'Database preparation failed: '.$db->error]);
        }

        $stmt->bind_param(
            'issssi',
            $siteCategoryId,
            $name,
            $idempotencyKey,
            $receivedAt,
            $receivedAt,
            $remoteId,
        );
    } else {
        $clientId = $requestId;

        $stmt = $db->prepare('INSERT INTO cms_topics (
            client_id, site_category_id, name, idempotency_key, received_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?
        )');

        if ($stmt === false) {
            cms_respond(500, ['error' => 'Database preparation failed: '.$db->error]);
        }

        $stmt->bind_param(
            'iissss',
            $clientId,
            $siteCategoryId,
            $name,
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
        'site_category_id' => $siteCategoryId,
        'name' => $name,
    ]);
}

function cms_delete_topic(mysqli $db, int $id): void
{
    $stmt = $db->prepare('DELETE FROM cms_topics WHERE id = ?');

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
        cms_respond(404, ['error' => 'Topic not found.']);
    }

    cms_respond(200, [
        'status' => 'deleted',
        'id' => $id,
    ]);
}

// --- Main ---------------------------------------------------------------------

cms_authenticate();

$db = cms_db();
cms_ensure_topic_table($db);

$method = cms_request_method();
$topicId = cms_requested_topic_id();

match (true) {
    $method === 'GET' && $topicId === null => cms_list_topics($db),
    $method === 'GET' && $topicId !== null => cms_show_topic($db, $topicId),
    $method === 'POST' && $topicId === null => cms_upsert_topic($db),
    $method === 'DELETE' && $topicId !== null => cms_delete_topic($db, $topicId),
    default => cms_respond(405, ['error' => 'Method not allowed for this URL.']),
};
