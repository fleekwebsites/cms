<?php

namespace App\Support;

use App\Enums\RemoteResource;
use App\Models\PendingRemoteWrite;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PendingRemoteWritePayloadStore
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: ?array<string, mixed>, payload_path: ?string}
     */
    public function persist(Site $site, RemoteResource $resource, string $resourceKey, array $payload): array
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $threshold = (int) config('cms.pending_writes.inline_payload_max_bytes', 262_144);

        if (strlen($encoded) <= $threshold) {
            return [
                'payload' => $payload,
                'payload_path' => null,
            ];
        }

        $path = $this->pathFor($site, $resource, $resourceKey);

        if (! Storage::disk('local')->put($path, $encoded)) {
            throw new RuntimeException('Unable to store the pending remote write payload on disk.');
        }

        return [
            'payload' => [],
            'payload_path' => $path,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(?array $payload, ?string $payloadPath): ?array
    {
        if ($payloadPath !== null && Storage::disk('local')->exists($payloadPath)) {
            $raw = Storage::disk('local')->get($payloadPath);

            if (! is_string($raw) || trim($raw) === '') {
                return $payload;
            }

            try {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $payload;
            }

            return $decoded;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function rewrite(PendingRemoteWrite $write, array $payload): void
    {
        if ($write->payload_path !== null) {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            Storage::disk('local')->put($write->payload_path, $encoded);

            return;
        }

        $write->update(['payload' => $payload]);
    }

    public function discard(?string $payloadPath): void
    {
        if ($payloadPath === null) {
            return;
        }

        Storage::disk('local')->delete($payloadPath);
    }

    private function pathFor(Site $site, RemoteResource $resource, string $resourceKey): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $resourceKey) ?? $resourceKey;
        $safeKey = trim($safeKey, '-');

        if ($safeKey === '') {
            $safeKey = 'write';
        }

        return sprintf(
            'pending-remote-writes/%d/%s/%s.json',
            $site->id,
            $resource->value,
            $safeKey,
        );
    }
}
