<?php

namespace App\Enums;

enum PendingRemoteWriteStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
        };
    }
}
