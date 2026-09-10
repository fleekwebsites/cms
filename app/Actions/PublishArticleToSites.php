<?php

namespace App\Actions;

use App\Enums\ArticleStatus;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\PublishLog;
use App\Models\Site;
use App\Support\ArticleContentFormatter;
use App\Support\PublishableImageEncoder;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PublishArticleToSites
{
    public function __construct(
        private ArticleContentFormatter $contentFormatter,
        private PublishableImageEncoder $imageEncoder,
    ) {}

    /**
     * @param  iterable<int, Site>  $sites
     * @return Collection<int, PublishLog>
     */
    public function handle(Article $article, iterable $sites): Collection
    {
        $article->loadMissing(['site:id,name', 'user:id,name']);

        if ($article->status === ArticleStatus::Published && $article->published_at === null) {
            $article->update(['published_at' => now()]);
            $article->refresh();
        }

        $sites = collect($sites)
            ->filter(fn (Site $site): bool => $site->is_active)
            ->values();

        if ($sites->isEmpty()) {
            return collect();
        }

        $payload = $this->payload($article);

        $responses = Http::pool(fn (Pool $pool) => $sites
            ->mapWithKeys(fn (Site $site) => [
                (string) $site->id => $pool->as((string) $site->id)
                    ->connectTimeout(config('cms.http.connect_timeout'))
                    ->timeout(config('cms.http.timeout'))
                    ->acceptJson()
                    ->withHeaders([
                        'X-API-Key' => $site->api_key,
                        'Idempotency-Key' => $article->uuid.'-'.$site->id,
                    ])
                    ->post($site->api_endpoint, $payload),
            ])
            ->all());

        return $sites->map(function (Site $site) use ($article, $payload, $responses): PublishLog {
            $result = $responses[(string) $site->id];

            $logPayload = $this->logPayload($payload);

            if ($result instanceof Throwable) {
                return $article->publishLogs()->create([
                    'site_id' => $site->id,
                    'request_payload' => $logPayload,
                    'response_code' => null,
                    'response_payload' => null,
                    'status' => PublishStatus::Failed,
                    'error_message' => $result->getMessage(),
                ]);
            }

            /** @var Response $result */
            $successful = $result->successful();

            return $article->publishLogs()->create([
                'site_id' => $site->id,
                'request_payload' => $logPayload,
                'response_code' => $result->status(),
                'response_payload' => $this->responseBody($result),
                'status' => $successful ? PublishStatus::Success : PublishStatus::Failed,
                'error_message' => $successful ? null : $result->reason(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Article $article): array
    {
        $payload = [
            'uuid' => $article->uuid,
            'slug' => $article->slug,
            'type' => $article->type->value,
            'layout' => $article->layout->value,
            'title' => $article->title,
            'content' => $this->contentFormatter->forPublish($article->content),
            'content_format' => 'html',
            'reading_time_minutes' => $article->readingTimeMinutes(),
        ];

        if (filled($article->excerpt)) {
            $payload['excerpt'] = $article->excerpt;
        }

        if (filled($article->keywords)) {
            $payload['keywords'] = $article->keywords;
        }

        if ($article->site_category_id !== null) {
            $payload['site_category_id'] = $article->site_category_id;
        }

        if ($article->author_id !== null) {
            $payload['author_id'] = $article->author_id;
        }

        if ($article->site !== null) {
            $payload['site_id'] = $article->site_id;
            $payload['site_name'] = $article->site->name;
        }

        if ($article->published_at !== null) {
            $payload['published_at'] = Carbon::parse($article->published_at)
                ->utc()
                ->format('Y-m-d H:i:s');
        }

        if (filled($article->featured_image_url)) {
            $encodedFeaturedImage = $this->imageEncoder->encodeFeaturedImage($article->featured_image_url);

            if ($encodedFeaturedImage !== null) {
                $payload = [...$payload, ...$encodedFeaturedImage];
            } else {
                $payload['featured_image_url'] = $article->featured_image_url;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function logPayload(array $payload): array
    {
        $logged = $payload;

        foreach (['content', 'featured_image_base64'] as $field) {
            if (! isset($logged[$field]) || ! is_string($logged[$field])) {
                continue;
            }

            $logged[$field] = '[omitted: '.strlen($logged[$field]).' bytes]';
        }

        return $logged;
    }

    private function responseBody(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            return Str::limit(
                (string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                10000,
                '',
            );
        }

        return Str::limit($response->body(), 10000, '');
    }
}
