<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Writer = 'writer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Writer => 'Writer',
        };
    }
}
