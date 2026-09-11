<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Complete => 'Complete',
            self::Published => 'Published',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Editing is still in progress.',
            self::Complete => 'Editing is finished — ready to publish.',
            self::Published => 'Live on the remote site.',
        };
    }

    public function isReadyToPublish(): bool
    {
        return $this === self::Complete;
    }
}
