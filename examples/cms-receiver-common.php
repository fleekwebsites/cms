<?php

declare(strict_types=1);

/**
 * Shared config + helpers for CMS receiver endpoints.
 *
 * Webuzo layout:
 *   public_html/api/cms/receive-cms-content.php
 *   public_html/api/cms/receive-cms-authors.php
 *   public_html/api/cms/receive-cms-categories.php
 *   public_html/api/cms/cms-receiver-common.php
 */

// --- Configuration (edit once for all receivers) ------------------------------

const CMS_API_KEY = 'replace-with-your-api-key';

/** Set true on Webuzo when using MySQL. */
const CMS_USE_MYSQL = false;

const CMS_MYSQL_DSN = 'mysql:host=127.0.0.1;dbname=your_database;charset=utf8mb4';
const CMS_MYSQL_USER = 'your_mysql_user';
const CMS_MYSQL_PASSWORD = 'your_mysql_password';

/** Used when CMS_USE_MYSQL is false (local/demo only). */
const CMS_SQLITE_PATH = __DIR__.'/storage/cms.sqlite';

/** Receiving-site table names (must match your schema or defaults below). */
const CMS_AUTHORS_TABLE = 'cms_authors';
const CMS_CATEGORIES_TABLE = 'cms_categories';

// --- HTTP helpers -------------------------------------------------------------

function cms_authenticate(): void
{
    $apiKey = cms_request_header('X-API-Key');

    if ($apiKey === null || ! hash_equals(CMS_API_KEY, $apiKey)) {
        cms_respond(401, ['error' => 'Invalid API key.']);
    }
}

/**
 * @return array<string, mixed>
 */
function cms_json_payload(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        cms_respond(405, ['error' => 'Method not allowed. Use POST.']);
    }

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

function cms_received_at(): string
{
    return gmdate('Y-m-d H:i:s');
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

function cms_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (CMS_USE_MYSQL) {
        $pdo = new PDO(CMS_MYSQL_DSN, CMS_MYSQL_USER, CMS_MYSQL_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }

    cms_ensure_directory(dirname(CMS_SQLITE_PATH));

    $pdo = new PDO('sqlite:'.CMS_SQLITE_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
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

function cms_table_name(string $table): string
{
    if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
        cms_respond(500, ['error' => 'Invalid table name configuration.']);
    }

    return $table;
}

function cms_ensure_author_table(PDO $pdo): void
{
    if (CMS_USE_MYSQL) {
        $table = cms_table_name(CMS_AUTHORS_TABLE);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `{$table}` (
                id INT UNSIGNED NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                credentials VARCHAR(255) NULL,
                bio TEXT NULL,
                idempotency_key VARCHAR(255) NULL,
                received_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        return;
    }

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS cms_authors (
            id INTEGER NOT NULL PRIMARY KEY,
            name TEXT NOT NULL,
            credentials TEXT,
            bio TEXT,
            idempotency_key TEXT,
            received_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    SQL);
}

function cms_ensure_category_table(PDO $pdo): void
{
    if (CMS_USE_MYSQL) {
        $table = cms_table_name(CMS_CATEGORIES_TABLE);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `{$table}` (
                id INT UNSIGNED NOT NULL PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                idempotency_key VARCHAR(255) NULL,
                received_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        return;
    }

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS cms_categories (
            id INTEGER NOT NULL PRIMARY KEY,
            name TEXT NOT NULL,
            idempotency_key TEXT,
            received_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    SQL);
}
