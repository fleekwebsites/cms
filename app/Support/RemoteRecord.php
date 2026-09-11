<?php

namespace App\Support;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RemoteRecord
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $key,
        public array $attributes = [],
    ) {}

    public function int(string $field): ?int
    {
        $value = $this->attributes[$field] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    public function string(string $field): ?string
    {
        $value = $this->attributes[$field] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function bool(string $field, bool $default = false): bool
    {
        $value = $this->attributes[$field] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $default;
    }

    public function routeKey(): string
    {
        return $this->key;
    }

    public function getRouteKey(): string
    {
        return $this->routeKey();
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return new self($value, $this->attributes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $key = self::resolveKey($payload);

        return new self($key, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveKey(array $payload): string
    {
        $uuid = isset($payload['uuid']) && is_string($payload['uuid']) ? trim($payload['uuid']) : '';

        if ($uuid !== '') {
            return $uuid;
        }

        $id = $payload['id'] ?? null;

        if (is_int($id)) {
            return (string) $id;
        }

        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        return Str::uuid()->toString();
    }

    public function displayLine(): string
    {
        $name = $this->string('name') ?? 'Unknown author';
        $credentials = $this->string('credentials');

        return $credentials === null
            ? $name
            : "{$name} · {$credentials}";
    }

    public function displayAuthorLine(): string
    {
        return $this->displayLine();
    }

    public function readingTimeMinutes(): int
    {
        $minutes = $this->int('reading_time_minutes');

        if ($minutes !== null && $minutes > 0) {
            return $minutes;
        }

        return app(ReadingTimeEstimator::class)->estimate(
            $this->string('content'),
            $this->string('excerpt'),
        );
    }

    public function type(): ArticleType
    {
        return ArticleType::tryFrom((string) ($this->attributes['type'] ?? '')) ?? ArticleType::Blog;
    }

    public function layout(): ArticleLayout
    {
        return ArticleLayout::tryFrom((string) ($this->attributes['layout'] ?? '')) ?? ArticleLayout::Default;
    }

    public function status(): ArticleStatus
    {
        return ArticleStatus::tryFrom((string) ($this->attributes['status'] ?? '')) ?? ArticleStatus::Draft;
    }

    public function updatedAt(): ?Carbon
    {
        $value = $this->string('updated_at') ?? $this->string('received_at');

        return $value === null ? null : Carbon::parse($value);
    }

    public function createdAt(): ?Carbon
    {
        $value = $this->string('created_at') ?? $this->string('received_at');

        return $value === null ? null : Carbon::parse($value);
    }

    /**
     * @param  Collection<int, RemoteRecord>  $authors
     */
    public function authorName(Collection $authors): ?string
    {
        $authorId = $this->int('author_id');

        if ($authorId === null) {
            return null;
        }

        $author = $authors->first(fn (RemoteRecord $record): bool => $record->int('id') === $authorId);

        return $author?->string('name');
    }

    /**
     * @param  Collection<int, RemoteRecord>  $categories
     */
    public function categoryName(Collection $categories): ?string
    {
        $categoryId = $this->int('site_category_id');

        if ($categoryId === null) {
            return null;
        }

        $category = $categories->first(fn (RemoteRecord $record): bool => $record->int('id') === $categoryId);

        return $category?->string('name');
    }

    /**
     * @param  Collection<int, RemoteRecord>  $topics
     */
    public function topicName(Collection $topics): ?string
    {
        $topicId = $this->int('topic_id');

        if ($topicId === null) {
            return null;
        }

        $topic = $topics->first(fn (RemoteRecord $record): bool => $record->int('id') === $topicId);

        return $topic?->string('name');
    }
}
