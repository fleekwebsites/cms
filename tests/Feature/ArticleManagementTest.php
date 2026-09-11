<?php

namespace Tests\Feature;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_create_a_blog_article_on_remote_site(): void
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
                'excerpt' => 'A short summary',
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

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/content')
            && $request['title'] === 'Launch announcement'
            && $request['editor_user_id'] === $writer->id);
    }

    public function test_author_must_exist_on_remote_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site);

        $this->actingAs($writer)
            ->from(route('sites.articles.create', $site))
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Faq->value,
                'title' => 'FAQ item',
                'content' => '<p>Answer</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'site_category_id' => 1,
                'author_id' => 999,
            ])
            ->assertRedirect(route('sites.articles.create', $site))
            ->assertSessionHasErrors('author_id');
    }

    public function test_category_must_exist_on_remote_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site);

        $this->actingAs($writer)
            ->from(route('sites.articles.create', $site))
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Faq->value,
                'title' => 'FAQ item',
                'content' => '<p>Answer</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'site_category_id' => 999,
                'author_id' => 1,
            ])
            ->assertRedirect(route('sites.articles.create', $site))
            ->assertSessionHasErrors('site_category_id');
    }

    public function test_writer_only_sees_their_articles_in_index(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response([
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Mine',
                    'type' => 'blog',
                    'layout' => 'default',
                    'status' => 'draft',
                    'content' => '<p>Hello</p>',
                    'editor_user_id' => $writer->id,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Someone else',
                    'type' => 'blog',
                    'layout' => 'default',
                    'status' => 'draft',
                    'content' => '<p>Hello</p>',
                    'editor_user_id' => $writer->id + 99,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($writer)->get(route('sites.articles.index', $site));

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Someone else');
    }

    public function test_admin_sees_all_articles_in_index(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response([
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Writer article',
                    'type' => 'blog',
                    'layout' => 'default',
                    'status' => 'draft',
                    'content' => '<p>Hello</p>',
                    'editor_user_id' => 999,
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->get(route('sites.articles.index', $site))
            ->assertOk()
            ->assertSee('Writer article');
    }

    public function test_writer_cannot_delete_an_article(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        $this->fakeRemoteSite($site, [
            "https://remote.test/api/cms/content/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'title' => 'Mine',
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'editor_user_id' => $writer->id,
            ], 200),
        ]);

        $this->actingAs($writer)
            ->delete(route('sites.articles.destroy', [$site, $uuid]))
            ->assertForbidden();
    }

    public function test_admin_can_delete_an_article_from_remote_site(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $uuid = (string) Str::uuid();

        Http::preventStrayRequests();
        Http::fake([
            "https://remote.test/api/cms/content/{$uuid}" => Http::sequence()
                ->push([
                    'uuid' => $uuid,
                    'title' => 'Delete me',
                    'type' => 'blog',
                    'layout' => 'default',
                    'status' => 'draft',
                    'content' => '<p>Hello</p>',
                ], 200)
                ->push(['status' => 'deleted'], 200),
            'https://remote.test/api/cms/authors*' => Http::response([], 200),
            'https://remote.test/api/cms/categories*' => Http::response([], 200),
        ]);

        $this->actingAs($admin)
            ->delete(route('sites.articles.destroy', [$site, $uuid]))
            ->assertRedirect(route('sites.articles.index', $site));
    }

    public function test_writer_without_delegation_cannot_open_a_site_workspace(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();

        $this->actingAs($writer)
            ->get(route('sites.articles.index', $site))
            ->assertForbidden();
    }
}
