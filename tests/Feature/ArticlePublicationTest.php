<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\AuthorName;
use App\Models\PublishLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticlePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_sends_mysql_datetime_and_pen_name_in_payload(): void
    {
        Http::preventStrayRequests();

        $writer = User::factory()->writer()->create(['name' => 'Account Writer']);
        $penName = AuthorName::factory()->for($writer)->create(['name' => 'Felix Ombui']);
        $article = Article::factory()->for($writer)->draft()->create([
            'author_name_id' => $penName->id,
            'title' => 'How to Prepare for Nursing Exams',
            'excerpt' => 'Essential tips for nursing exam success',
            'keywords' => 'nursing, exams, study tips',
            'content' => '<p>Nursing exams require...</p>',
            'featured_image_url' => 'https://cms.test/images/featured.jpg',
        ]);
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        Http::fake([
            'remote.test/*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('articles.publications.store', $article), [
                'site_ids' => [$site->id],
            ])
            ->assertRedirect(route('articles.show', $article));

        $article->refresh();

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);

        $publishedAt = $article->published_at->utc()->format('Y-m-d H:i:s');

        Http::assertSent(function ($request) use ($site, $publishedAt) {
            return $request->url() === $site->api_endpoint
                && $request->hasHeader('X-API-Key', $site->api_key)
                && $request['title'] === 'How to Prepare for Nursing Exams'
                && $request['content'] === '<p>Nursing exams require...</p>'
                && $request['excerpt'] === 'Essential tips for nursing exam success'
                && $request['keywords'] === 'nursing, exams, study tips'
                && $request['author_name'] === 'Felix Ombui'
                && $request['featured_image_url'] === 'https://cms.test/images/featured.jpg'
                && $request['published_at'] === $publishedAt;
        });
    }

    public function test_failed_delivery_is_logged_with_error_details(): void
    {
        Http::preventStrayRequests();

        $writer = User::factory()->writer()->create();
        $article = Article::factory()->for($writer)->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        Http::fake([
            'remote.test/*' => Http::response(['error' => 'invalid key'], 401),
        ]);

        $this->actingAs($writer)
            ->post(route('articles.publications.store', $article), [
                'site_ids' => [$site->id],
            ])
            ->assertRedirect(route('articles.show', $article));

        $log = PublishLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame(PublishStatus::Failed, $log->status);
        $this->assertSame(401, $log->response_code);
    }
}
