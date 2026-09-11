<?php

namespace Tests\Feature;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\PendingRemoteWriteStatus;
use App\Enums\RemoteResource;
use App\Models\PendingRemoteWrite;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemoteGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_store_posts_to_remote_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Blog->value,
                'title' => 'Launch announcement',
                'content' => '<p>Hello world</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'site_category_id' => 1,
                'topic_id' => 1,
                'author_id' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('articles', [
            'title' => 'Launch announcement',
        ]);
    }

    public function test_unreachable_remote_site_queues_temporary_article_write(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response([], 500),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Blog->value,
                'title' => 'Queued article',
                'content' => '<p>Hello world</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'site_category_id' => 1,
                'topic_id' => 1,
                'author_id' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('pending_remote_writes', [
            'site_id' => $site->id,
            'user_id' => $writer->id,
            'resource' => RemoteResource::Articles->value,
            'status' => PendingRemoteWriteStatus::Pending->value,
        ]);
    }

    public function test_large_article_payload_is_queued_on_disk_instead_of_mysql(): void
    {
        Storage::fake('local');
        config(['cms.pending_writes.inline_payload_max_bytes' => 1024]);

        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response([], 500),
        ]);

        $largeContent = '<p>'.str_repeat('A', 2048).'</p>';

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Blog->value,
                'title' => 'Large queued article',
                'content' => $largeContent,
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'site_category_id' => 1,
                'topic_id' => 1,
                'author_id' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $write = PendingRemoteWrite::query()->first();

        $this->assertNotNull($write);
        $this->assertSame('[]', $write->getAttributes()['payload']);
        $this->assertNotNull($write->payload_path);
        $this->assertSame('Large queued article', $write->resolvedPayload()['title'] ?? null);
        Storage::disk('local')->assertExists($write->payload_path);
    }

    public function test_article_index_shows_unreachable_banner(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        Http::preventStrayRequests();
        Http::fake([
            'https://remote.test/api/cms/content*' => Http::response([], 500),
        ]);

        $this->actingAs($writer)
            ->get(route('sites.articles.index', $site))
            ->assertOk()
            ->assertSee('Remote site unreachable');
    }

    public function test_flush_command_retries_pending_writes(): void
    {
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        PendingRemoteWrite::factory()->for($site)->create([
            'resource' => RemoteResource::Articles,
            'resource_key' => '11111111-1111-1111-1111-111111111111',
            'payload' => [
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'title' => 'Retry me',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'content_format' => 'html',
            ],
            'idempotency_key' => '11111111-1111-1111-1111-111111111111',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->artisan('cms:flush-pending-writes')
            ->assertSuccessful();

        $this->assertDatabaseHas('pending_remote_writes', [
            'site_id' => $site->id,
            'resource_key' => '11111111-1111-1111-1111-111111111111',
            'status' => PendingRemoteWriteStatus::Sent->value,
        ]);
    }
}
