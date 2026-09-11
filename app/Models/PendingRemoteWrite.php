<?php

namespace App\Models;

use App\Enums\PendingRemoteWriteStatus;
use App\Enums\RemoteResource;
use App\Support\PendingRemoteWritePayloadStore;
use Database\Factories\PendingRemoteWriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'user_id',
    'resource',
    'resource_key',
    'method',
    'payload',
    'payload_path',
    'idempotency_key',
    'status',
    'error_message',
    'attempts',
    'last_attempted_at',
    'sent_at',
])]
class PendingRemoteWrite extends Model
{
    /** @use HasFactory<PendingRemoteWriteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => PendingRemoteWriteStatus::class,
            'resource' => RemoteResource::class,
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvedPayload(): ?array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $this->payload;

        return app(PendingRemoteWritePayloadStore::class)->resolve($payload, $this->payload_path);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayTitle(): string
    {
        $payload = $this->resolvedPayload();

        if (! is_array($payload)) {
            return $this->resource->label().' '.$this->resource_key;
        }

        $title = $payload['title'] ?? $payload['name'] ?? null;

        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        return $this->resource->label().' '.$this->resource_key;
    }
}
