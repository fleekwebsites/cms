<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticlePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_publish_on_delegated_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $this->grantSiteAccess($writer, $site);

        $this->actingAs($writer);

        $this->assertTrue($writer->can('publish', [Article::class, $site]));
    }

    public function test_writer_cannot_access_another_users_remote_article(): void
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
                'title' => 'Other article',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'editor_user_id' => 99999,
            ], 200),
            'https://remote.test/api/cms/authors*' => Http::response([], 200),
            'https://remote.test/api/cms/categories*' => Http::response([], 200),
        ]);

        $this->actingAs($writer)
            ->get(route('sites.articles.show', [$site, $uuid]))
            ->assertNotFound();
    }

    public function test_admin_can_manage_legacy_article_records(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('view', $article));
        $this->assertTrue($admin->can('update', $article));
        $this->assertTrue($admin->can('delete', $article));
    }

    public function test_writer_can_view_update_and_publish_their_own_legacy_article(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $this->grantSiteAccess($writer, $site);
        $article = Article::factory()->for($writer)->for($site)->create();

        $this->actingAs($writer);

        $this->assertTrue($writer->can('view', $article));
        $this->assertTrue($writer->can('update', $article));
        $this->assertTrue($writer->can('publish', $article));
        $this->assertFalse($writer->can('delete', $article));
    }
}
