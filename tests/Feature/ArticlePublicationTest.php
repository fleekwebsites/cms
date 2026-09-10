<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Author;
use App\Models\PublishLog;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticlePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_sends_author_and_category_ids_only(): void
    {
        Http::preventStrayRequests();

        $writer = User::factory()->writer()->create(['name' => 'Account Writer']);
        $site = Site::factory()->create([
            'name' => 'Nursing Elites',
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $author = Author::factory()->for($site)->create([
            'name' => 'Felix Ombui',
            'credentials' => 'DNP, FNP-BC',
            'bio' => 'Elena Marsh is a family nurse practitioner.',
        ]);
        $category = SiteCategory::factory()->for($site)->create(['name' => 'NP Programs']);
        $article = Article::factory()->for($writer)->draft()->create([
            'site_id' => $site->id,
            'site_category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'How to Prepare for Nursing Exams',
            'excerpt' => 'Essential tips for nursing exam success',
            'keywords' => 'nursing, exams, study tips',
            'content' => '<p class="ql-align-center">Nursing exams require...</p>',
            'featured_image_url' => 'https://cms.test/images/featured.jpg',
        ]);

        Http::fake([
            'remote.test/*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('articles.publications.store', $article))
            ->assertRedirect(route('articles.show', $article));

        $article->refresh();

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);

        $publishedAt = $article->published_at->utc()->format('Y-m-d H:i:s');

        Http::assertSent(function ($request) use ($article, $site, $author, $category, $publishedAt) {
            return $request->url() === $site->api_endpoint
                && $request->hasHeader('X-API-Key', $site->api_key)
                && $request['uuid'] === $article->uuid
                && $request['site_id'] === $site->id
                && $request['site_name'] === 'Nursing Elites'
                && $request['site_category_id'] === $category->id
                && $request['author_id'] === $author->id
                && ! array_key_exists('category', $request->data())
                && ! array_key_exists('author_name', $request->data())
                && ! array_key_exists('author_credentials', $request->data())
                && ! array_key_exists('author_bio', $request->data())
                && $request['reading_time_minutes'] >= 1
                && $request['published_at'] === $publishedAt;
        });
    }

    public function test_publishing_sends_inline_text_color_and_highlight_styles(): void
    {
        Http::preventStrayRequests();

        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $article = Article::factory()->for($writer)->draft()->create([
            'site_id' => $site->id,
            'content' => '<p><span class="ql-color-e60000">Red text</span> and <span style="background-color: #ffff00;">Highlighted text</span></p>',
        ]);

        Http::fake([
            'remote.test/*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('articles.publications.store', $article))
            ->assertRedirect(route('articles.show', $article));

        Http::assertSent(function ($request) {
            return str_contains((string) $request['content'], 'color:#e60000')
                && str_contains((string) $request['content'], 'background-color: #ffff00')
                && ! str_contains((string) $request['content'], 'ql-color-');
        });
    }

    public function test_failed_delivery_is_logged_with_error_details(): void
    {
        Http::preventStrayRequests();

        $writer = User::factory()->writer()->create();
        $article = Article::factory()->for($writer)->create();
        $site = Site::query()->findOrFail($article->site_id);

        Http::fake([
            'remote.test/*' => Http::response(['error' => 'invalid key'], 401),
        ]);

        $site->update(['api_endpoint' => 'https://remote.test/api/cms/content']);

        $this->actingAs($writer)
            ->post(route('articles.publications.store', $article))
            ->assertRedirect(route('articles.show', $article));

        $log = PublishLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame(PublishStatus::Failed, $log->status);
        $this->assertSame(401, $log->response_code);
    }

    public function test_publish_logs_store_a_summary_payload_instead_of_full_content(): void
    {
        Http::preventStrayRequests();

        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $article = Article::factory()->for($writer)->create([
            'site_id' => $site->id,
            'content' => '<p>'.str_repeat('Large article body. ', 5000).'</p>',
        ]);

        Http::fake([
            'remote.test/*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('articles.publications.store', $article))
            ->assertRedirect(route('articles.show', $article));

        $log = PublishLog::query()->first();

        $this->assertNotNull($log);
        $this->assertIsString($log->request_payload['content']);
        $this->assertStringStartsWith('[omitted:', $log->request_payload['content']);
        $this->assertStringNotContainsString('Large article body.', $log->request_payload['content']);
    }
}
