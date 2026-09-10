<?php

/**
 * CMS author receiver.
 *
 * Endpoint (derived from content URL in CMS):
 *   https://yoursite.com/api/cms/receive-cms-authors.php
 *
 * JSON POST payload from CMS:
 *   { "id": 1, "name": "...", "credentials": "...", "bio": "..." }
 *
 * Headers:
 *   X-API-Key, Idempotency-Key (optional)
 */

declare(strict_types=1);

require_once __DIR__.'/cms-receiver-common.php';

cms_authenticate();

/** @var array<string, mixed> $payload */
$payload = cms_json_payload();

$id = cms_required_positive_int($payload, 'id');
$name = cms_required_string($payload, 'name');
$credentials = cms_string_or_null($payload['credentials'] ?? null);
$bio = cms_string_or_null($payload['bio'] ?? null);
$receivedAt = cms_received_at();

$pdo = cms_pdo();
cms_ensure_author_table($pdo);

if (CMS_USE_MYSQL) {
    $table = cms_table_name(CMS_AUTHORS_TABLE);

    $statement = $pdo->prepare(<<<SQL
        INSERT INTO `{$table}` (
            id, name, credentials, bio, idempotency_key, received_at, updated_at
        ) VALUES (
            :id, :name, :credentials, :bio, :idempotency_key, :received_at, :updated_at
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            credentials = VALUES(credentials),
            bio = VALUES(bio),
            idempotency_key = VALUES(idempotency_key),
            received_at = VALUES(received_at),
            updated_at = VALUES(updated_at)
    SQL);
} else {
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO cms_authors (
            id, name, credentials, bio, idempotency_key, received_at, updated_at
        ) VALUES (
            :id, :name, :credentials, :bio, :idempotency_key, :received_at, :updated_at
        )
        ON CONFLICT(id) DO UPDATE SET
            name = excluded.name,
            credentials = excluded.credentials,
            bio = excluded.bio,
            idempotency_key = excluded.idempotency_key,
            received_at = excluded.received_at,
            updated_at = excluded.updated_at
    SQL);
}

$statement->execute([
    'id' => $id,
    'name' => $name,
    'credentials' => $credentials,
    'bio' => $bio,
    'idempotency_key' => cms_request_header('Idempotency-Key'),
    'received_at' => $receivedAt,
    'updated_at' => $receivedAt,
]);

cms_respond(201, [
    'status' => 'accepted',
    'id' => $id,
    'name' => $name,
    'credentials' => $credentials,
]);
