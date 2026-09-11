<?php

namespace App\Enums;

enum RemoteResource: string
{
    case Articles = 'articles';
    case Authors = 'authors';
    case Categories = 'categories';
    case Topics = 'topics';

    public function label(): string
    {
        return match ($this) {
            self::Articles => 'Article',
            self::Authors => 'Author',
            self::Categories => 'Category',
            self::Topics => 'Topic',
        };
    }

    public function usesClientIds(): bool
    {
        return $this !== self::Articles;
    }
}
