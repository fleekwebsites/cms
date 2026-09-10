<?php

namespace App\Support;

class ReadingTimeEstimator
{
    private const WORDS_PER_MINUTE = 200;

    public function estimate(?string $content, ?string $excerpt = null): int
    {
        $text = trim(strip_tags(trim(($content ?? '').' '.($excerpt ?? ''))));

        if ($text === '') {
            return 1;
        }

        $wordCount = str_word_count($text);

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }
}
