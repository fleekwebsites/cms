<?php

namespace Tests\Feature;

use App\Enums\PendingRemoteWriteStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticlePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_sends_author_and_category_ids_only(): void
    {
        $writer = User::factory()->writer()->create(['name' => 'Account Writer']);
        $site = Site::factory()->create([
            'name' => 'Nursing Elites',
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'How to Prepare for Nursing Exams',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'complete',
                'content' => '<p>Nursing exams require...</p>',
                'site_category_id' => 1,
                'author_id' => 1,
                'editor_user_id' => $writer->id,
            ], 200),
            'https://remote.test/api/cms/categories*' => Http::response([['id' => 1, 'name' => 'General']], 200),
            'https://remote.test/api/cms/authors*' => Http::response([['id' => 1, 'name' => 'Author']], 200),
            'https://remote.test/api/cms/topics*' => Http::response([], 200),
            'https://remote.test/api/cms/content/' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.publications.store', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.show', [$site, $uuid]))
            ->assertSessionHas('status');

        Http::assertSent(function ($request) use ($uuid, $site): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $data = json_decode($request->body(), true);

            if (! is_array($data)) {
                return false;
            }

            return str_contains($request->url(), '/content')
                && ($data['uuid'] ?? null) === $uuid
                && ($data['site_id'] ?? null) === $site->id
                && ($data['site_name'] ?? null) === 'Nursing Elites'
                && ($data['site_category_id'] ?? null) === 1
                && ($data['author_id'] ?? null) === 1
                && ($data['status'] ?? null) === 'published'
                && ! array_key_exists('author_name', $data);
        });
    }

    public function test_publishing_sends_inline_text_color_and_highlight_styles(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'Styled article',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'complete',
                'content' => '<p><span class="ql-color-e60000">Red text</span> and <span style="background-color: #ffff00;">Highlighted text</span></p>',
                'editor_user_id' => $writer->id,
            ], 200),
            'https://remote.test/api/cms/categories*' => Http::response([['id' => 1, 'name' => 'General']], 200),
            'https://remote.test/api/cms/authors*' => Http::response([['id' => 1, 'name' => 'Author']], 200),
            'https://remote.test/api/cms/topics*' => Http::response([], 200),
            'https://remote.test/api/cms/content/' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.publications.store', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.show', [$site, $uuid]));

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $content = (string) ($request->data()['content'] ?? '');

            return str_contains($content, 'color:#e60000')
                && str_contains($content, 'background-color: #ffff00');
        });
    }

    public function test_draft_articles_can_be_marked_complete(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'Draft article',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'editor_user_id' => $writer->id,
            ], 200),
            'https://remote.test/api/cms/content/' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.completion.store', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.show', [$site, $uuid]))
            ->assertSessionHas('status');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/content')
            && ($request['status'] ?? null) === 'complete');
    }

    public function test_draft_articles_cannot_be_published(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'Still drafting',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'editor_user_id' => $writer->id,
            ], 200),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.publications.store', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.show', [$site, $uuid]))
            ->assertSessionHas('error');

        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
    }

    public function test_failed_delivery_is_queued_for_retry(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'Retry me',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'complete',
                'content' => '<p>Hello</p>',
                'editor_user_id' => $writer->id,
            ], 200),
            'https://remote.test/api/cms/content*' => Http::response(['error' => 'invalid key'], 401),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.publications.store', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.show', [$site, $uuid]))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('pending_remote_writes', [
            'resource_key' => $uuid,
            'status' => PendingRemoteWriteStatus::Pending->value,
        ]);
    }

    public function test_unreachable_remote_site_queues_publish_attempt(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'Retry me',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'complete',
                'content' => '<p>Hello</p>',
                'editor_user_id' => $writer->id,
            ], 200),
            'https://remote.test/api/cms/content*' => Http::response([], 500),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.publications.store', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.show', [$site, $uuid]))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('pending_remote_writes', [
            'site_id' => $site->id,
            'resource_key' => $uuid,
            'status' => PendingRemoteWriteStatus::Pending->value,
        ]);
    }
}
