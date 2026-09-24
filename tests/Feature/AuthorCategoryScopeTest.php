<?php

namespace Tests\Feature;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\AuthorCategoryAssignment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorCategoryScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_rejects_author_scoped_to_another_category(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/categories*' => Http::response([
                ['id' => 1, 'name' => 'Nursing'],
                ['id' => 2, 'name' => 'High school exams'],
            ], 200),
            'https://remote.test/api/cms/content/authors*' => Http::response([
                ['id' => 1, 'name' => 'Nurse Author'],
                ['id' => 2, 'name' => 'Exam Author'],
            ], 200),
        ]);

        AuthorCategoryAssignment::query()->create([
            'site_id' => $site->id,
            'author_id' => 1,
            'category_id' => 1,
        ]);

        $this->actingAs($writer)
            ->from(route('sites.articles.create', $site))
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Faq->value,
                'title' => 'Wrong pairing',
                'content' => '<p>Body</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'site_category_id' => 2,
                'author_id' => 1,
            ])
            ->assertRedirect(route('sites.articles.create', $site))
            ->assertSessionHasErrors('author_id');
    }

    public function test_author_options_endpoint_filters_by_category(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/categories*' => Http::response([
                ['id' => 1, 'name' => 'Nursing'],
                ['id' => 2, 'name' => 'High school exams'],
            ], 200),
            'https://remote.test/api/cms/content/authors*' => Http::response([
                ['id' => 1, 'name' => 'Nurse Author'],
                ['id' => 2, 'name' => 'Exam Author'],
            ], 200),
        ]);

        AuthorCategoryAssignment::query()->create([
            'site_id' => $site->id,
            'author_id' => 1,
            'category_id' => 1,
        ]);

        AuthorCategoryAssignment::query()->create([
            'site_id' => $site->id,
            'author_id' => 2,
            'category_id' => 2,
        ]);

        $response = $this->actingAs($writer)
            ->getJson(route('sites.authors.options', $site).'?site_category_id=1')
            ->assertOk();

        $this->assertSame([1], collect($response->json())->pluck('id')->all());
    }
}
