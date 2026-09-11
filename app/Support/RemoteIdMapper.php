<?php

namespace App\Support;

use App\Enums\PendingRemoteWriteStatus;
use App\Enums\RemoteResource;
use App\Models\PendingRemoteWrite;
use App\Models\RemoteIdMapping;
use App\Models\Site;
use Illuminate\Http\Client\Response;

class RemoteIdMapper
{
    public function __construct(private PendingRemoteWritePayloadStore $payloadStore) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncFromResponse(Site $site, RemoteResource $resource, array $payload, Response $response): ?int
    {
        if (! $response->successful() || ! $resource->usesClientIds()) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        $remoteId = $this->intFromMixed($body['id'] ?? null);
        $clientId = $this->intFromMixed($body['client_id'] ?? ($payload['id'] ?? null));

        if ($remoteId === null) {
            return null;
        }

        if ($clientId !== null && $clientId !== $remoteId) {
            $this->recordMapping($site, $resource, $clientId, $remoteId);
        }

        return $remoteId;
    }

    public function recordMapping(Site $site, RemoteResource $resource, int $clientId, int $remoteId): void
    {
        if ($clientId === $remoteId) {
            return;
        }

        RemoteIdMapping::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'resource' => $resource,
                'client_id' => $clientId,
            ],
            [
                'remote_id' => $remoteId,
            ],
        );

        $this->rewritePendingPayloads($site, $resource, $clientId, $remoteId);
    }

    public function resolveRemoteId(Site $site, RemoteResource $resource, int $id): int
    {
        $mapping = RemoteIdMapping::query()
            ->where('site_id', $site->id)
            ->where('resource', $resource)
            ->where('client_id', $id)
            ->value('remote_id');

        return is_int($mapping) ? $mapping : $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function translateArticlePayload(Site $site, array $payload): array
    {
        if (isset($payload['site_category_id'])) {
            $payload['site_category_id'] = $this->resolveRemoteId(
                $site,
                RemoteResource::Categories,
                (int) $payload['site_category_id'],
            );
        }

        if (isset($payload['author_id'])) {
            $payload['author_id'] = $this->resolveRemoteId(
                $site,
                RemoteResource::Authors,
                (int) $payload['author_id'],
            );
        }

        if (isset($payload['topic_id'])) {
            $payload['topic_id'] = $this->resolveRemoteId(
                $site,
                RemoteResource::Topics,
                (int) $payload['topic_id'],
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function translateTopicPayload(Site $site, array $payload): array
    {
        if (isset($payload['site_category_id'])) {
            $payload['site_category_id'] = $this->resolveRemoteId(
                $site,
                RemoteResource::Categories,
                (int) $payload['site_category_id'],
            );
        }

        return $payload;
    }

    private function rewritePendingPayloads(
        Site $site,
        RemoteResource $resource,
        int $clientId,
        int $remoteId,
    ): void {
        $pending = PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->get();

        foreach ($pending as $write) {
            $payload = $write->resolvedPayload();

            if (! is_array($payload)) {
                continue;
            }

            $changed = false;

            foreach ($this->payloadFieldsFor($resource) as $field) {
                if (($payload[$field] ?? null) === $clientId) {
                    $payload[$field] = $remoteId;
                    $changed = true;
                }
            }

            if ($changed) {
                $this->payloadStore->rewrite($write, $payload);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function payloadFieldsFor(RemoteResource $resource): array
    {
        return match ($resource) {
            RemoteResource::Categories => ['site_category_id'],
            RemoteResource::Authors => ['author_id'],
            RemoteResource::Topics => ['topic_id'],
            RemoteResource::Articles => [],
        };
    }

    private function intFromMixed(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
