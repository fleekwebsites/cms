<?php

namespace App\Support;

use App\Models\PendingRemoteWrite;

class RemoteSendResult
{
    public function __construct(
        public bool $successful,
        public bool $queued,
        public ?PendingRemoteWrite $pending = null,
        public ?string $message = null,
        public ?int $responseCode = null,
        public ?int $remoteId = null,
    ) {}

    public static function delivered(?int $responseCode = null, ?int $remoteId = null): self
    {
        return new self(
            successful: true,
            queued: false,
            responseCode: $responseCode,
            remoteId: $remoteId,
        );
    }

    public static function queued(PendingRemoteWrite $pending, ?string $message = null): self
    {
        return new self(
            successful: false,
            queued: true,
            pending: $pending,
            message: $message ?? 'The remote site is unreachable. Your changes were saved temporarily and will be sent when the site is available.',
        );
    }

    public static function failed(string $message, ?int $responseCode = null): self
    {
        return new self(
            successful: false,
            queued: false,
            message: $message,
            responseCode: $responseCode,
        );
    }
}
