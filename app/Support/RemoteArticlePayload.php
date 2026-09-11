<?php

namespace App\Support;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RemoteArticlePayload
{
    public function __construct(
        private ArticleContentFormatter $contentFormatter,
        private PublishableImageEncoder $imageEncoder,
        private ReadingTimeEstimator $readingTime,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function fromRequest(
        array $validated,
        Site $site,
        User $editor,
        ?RemoteRecord $existing = null,
        ?UploadedFile $featuredImage = null,
    ): array {
        $uuid = $existing?->string('uuid') ?? (string) Str::uuid();
        $title = is_string($validated['title'] ?? null) ? trim($validated['title']) : '';
        $type = ArticleType::from((string) $validated['type']);
        $status = ArticleStatus::from((string) $validated['status']);
        $content = $this->contentFormatter->normalize((string) $validated['content']);
        $publishedAt = $existing?->string('published_at');

        if ($status === ArticleStatus::Published && $publishedAt === null) {
            $publishedAt = now()->utc()->format('Y-m-d H:i:s');
        }

        if ($status !== ArticleStatus::Published) {
            $publishedAt = null;
        }

        $layout = $type === ArticleType::Faq
            ? (string) ($validated['layout'] ?? ArticleLayout::Default->value)
            : ArticleLayout::Default->value;

        $payload = [
            'uuid' => $uuid,
            'slug' => $this->uniqueSlug($title, $existing?->string('slug')),
            'type' => $type->value,
            'layout' => $layout,
            'title' => $title,
            'content' => $this->contentFormatter->forPublish($content),
            'content_format' => 'html',
            'status' => $status->value,
            'reading_time_minutes' => $this->readingTime->estimate($content, is_string($validated['excerpt'] ?? null) ? $validated['excerpt'] : null),
            'site_id' => $site->id,
            'site_name' => $site->name,
            'editor_user_id' => $editor->id,
            'editor_name' => $editor->name,
        ];

        $excerpt = is_string($validated['excerpt'] ?? null) ? trim($validated['excerpt']) : '';

        if ($excerpt !== '') {
            $payload['excerpt'] = $excerpt;
        }

        $keywords = is_string($validated['keywords'] ?? null) ? trim($validated['keywords']) : '';

        if ($keywords !== '') {
            $payload['keywords'] = $keywords;
        }

        if (isset($validated['site_category_id'])) {
            $payload['site_category_id'] = (int) $validated['site_category_id'];
        }

        if ($type === ArticleType::Blog && isset($validated['topic_id'])) {
            $payload['topic_id'] = (int) $validated['topic_id'];
        }

        if (isset($validated['author_id'])) {
            $payload['author_id'] = (int) $validated['author_id'];
        }

        if ($publishedAt !== null) {
            $payload['published_at'] = Carbon::parse($publishedAt)->utc()->format('Y-m-d H:i:s');
        }

        $featuredImageUrl = is_string($validated['featured_image_url'] ?? null)
            ? trim($validated['featured_image_url'])
            : ($existing?->string('featured_image_url') ?? '');

        if ($featuredImage instanceof UploadedFile) {
            $path = $featuredImage->store('article-images', 'public');
            $featuredImageUrl = asset('storage/'.$path);
        }

        if ($featuredImageUrl !== '') {
            $encodedFeaturedImage = $this->imageEncoder->encodeFeaturedImage($featuredImageUrl);

            if ($encodedFeaturedImage !== null) {
                $payload = [...$payload, ...$encodedFeaturedImage];
            } else {
                $payload['featured_image_url'] = $featuredImageUrl;
            }
        }

        return $payload;
    }

    private function uniqueSlug(string $title, ?string $existingSlug = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'article';
        }

        if ($existingSlug !== null && Str::startsWith($existingSlug, $base)) {
            return $existingSlug;
        }

        return $base.'-'.Str::lower(Str::random(6));
    }
}
