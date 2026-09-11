<?php

namespace App\Support;

use App\Enums\PendingRemoteWriteStatus;
use App\Enums\RemoteResource;
use App\Models\PendingRemoteWrite;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PendingRemoteWriteQueue
{
    public function __construct(private PendingRemoteWritePayloadStore $payloadStore) {}

    private function reconnectIfNeeded(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::reconnect();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(
        Site $site,
        RemoteResource $resource,
        string $resourceKey,
        array $payload,
        string $idempotencyKey,
        ?int $userId,
        ?string $errorMessage = null,
    ): PendingRemoteWrite {
        $this->reconnectIfNeeded();

        $existing = PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('resource', $resource->value)
            ->where('resource_key', $resourceKey)
            ->first();

        if ($existing?->payload_path !== null) {
            $this->payloadStore->discard($existing->payload_path);
        }

        $storedPayload = $this->payloadStore->persist($site, $resource, $resourceKey, $payload);

        return PendingRemoteWrite::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'resource' => $resource->value,
                'resource_key' => $resourceKey,
            ],
            [
                'user_id' => $userId,
                'method' => 'POST',
                'payload' => $storedPayload['payload'],
                'payload_path' => $storedPayload['payload_path'],
                'idempotency_key' => $idempotencyKey,
                'status' => PendingRemoteWriteStatus::Pending,
                'error_message' => $errorMessage,
                'attempts' => 0,
                'last_attempted_at' => null,
                'sent_at' => null,
            ],
        );
    }

    public function storeDelete(
        Site $site,
        RemoteResource $resource,
        string $resourceKey,
        string $idempotencyKey,
        ?int $userId,
        ?string $errorMessage = null,
    ): PendingRemoteWrite {
        $this->reconnectIfNeeded();

        $existing = PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('resource', $resource->value)
            ->where('resource_key', $resourceKey)
            ->first();

        if ($existing?->payload_path !== null) {
            $this->payloadStore->discard($existing->payload_path);
        }

        return PendingRemoteWrite::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'resource' => $resource->value,
                'resource_key' => $resourceKey,
            ],
            [
                'user_id' => $userId,
                'method' => 'DELETE',
                'payload' => [],
                'payload_path' => null,
                'idempotency_key' => $idempotencyKey,
                'status' => PendingRemoteWriteStatus::Pending,
                'error_message' => $errorMessage,
                'attempts' => 0,
                'last_attempted_at' => null,
                'sent_at' => null,
            ],
        );
    }

    public function markSent(Site $site, RemoteResource $resource, string $resourceKey): void
    {
        $writes = PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('resource', $resource->value)
            ->where('resource_key', $resourceKey)
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->get();

        foreach ($writes as $write) {
            $this->payloadStore->discard($write->payload_path);
        }

        PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('resource', $resource->value)
            ->where('resource_key', $resourceKey)
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->update([
                'status' => PendingRemoteWriteStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
                'payload' => [],
                'payload_path' => null,
            ]);
    }

    /**
     * @return Collection<int, RemoteRecord>
     */
    public function mergePendingArticles(Site $site, Collection $remoteArticles): Collection
    {
        $pending = PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('resource', RemoteResource::Articles->value)
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->latest()
            ->get();

        $merged = $remoteArticles->keyBy(fn (RemoteRecord $record): string => $record->key);

        foreach ($pending as $write) {
            $payload = $write->resolvedPayload();

            if ($payload === null) {
                continue;
            }

            $record = RemoteRecord::fromPayload([
                ...$payload,
                'pending_remote_write_id' => $write->id,
                'pending_sync' => true,
                'updated_at' => $write->updated_at?->toIso8601String(),
            ]);

            $merged->put($record->key, $record);
        }

        return $merged->values()
            ->sortByDesc(fn (RemoteRecord $record): int => $record->updatedAt()?->getTimestamp() ?? 0)
            ->values();
    }

    public function pendingCountForSites(array $siteIds): int
    {
        if ($siteIds === []) {
            return 0;
        }

        return PendingRemoteWrite::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->count();
    }

    /**
     * @return Collection<int, PendingRemoteWrite>
     */
    public function recentForSites(array $siteIds, int $limit = 5): Collection
    {
        if ($siteIds === []) {
            return collect();
        }

        return PendingRemoteWrite::query()
            ->with(['site:id,name', 'user:id,name'])
            ->whereIn('site_id', $siteIds)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
