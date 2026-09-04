<?php

namespace App\Enums;

enum ArticleLayout: string
{
    case Default = 'default';
    case Featured = 'featured';
    case Magazine = 'magazine';
    case Minimal = 'minimal';
    case Split = 'split';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Featured => 'Featured',
            self::Magazine => 'Magazine',
            self::Minimal => 'Minimal',
            self::Split => 'Split',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Default => 'Standard single-column article',
            self::Featured => 'Hero image with prominent title',
            self::Magazine => 'Editorial two-column reading layout',
            self::Minimal => 'Clean text-first layout',
            self::Split => 'Sidebar alongside the article body',
        };
    }
}
