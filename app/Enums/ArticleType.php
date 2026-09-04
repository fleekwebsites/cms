<?php

namespace App\Enums;

enum ArticleType: string
{
    case Blog = 'blog';
    case Faq = 'faq';

    public function label(): string
    {
        return match ($this) {
            self::Blog => 'Blog',
            self::Faq => 'FAQ',
        };
    }
}
