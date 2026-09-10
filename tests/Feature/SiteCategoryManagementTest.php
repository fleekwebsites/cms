<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_category_to_site(): void
    {
        Http::preventStrayRequests();

        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        Http::fake([
            'remote.test/api/cms/categories' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.categories.store', $site), [
                'name' => 'NP Programs',
            ])
            ->assertRedirect(route('sites.show', $site));

        $category = SiteCategory::query()->first();

        $this->assertNotNull($category);
        $this->assertDatabaseHas('site_categories', [
            'site_id' => $site->id,
            'name' => 'NP Programs',
        ]);

        Http::assertSent(function ($request) use ($category) {
            return $request->url() === 'https://remote.test/api/cms/categories'
                && $request['id'] === $category->id
                && $request['name'] === 'NP Programs';
        });
    }

    public function test_writer_can_fetch_categories_for_active_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        SiteCategory::factory()->for($site)->create(['name' => 'Burnout']);

        $this->actingAs($writer)
            ->getJson(route('sites.categories.index', $site))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Burnout']);
    }

    public function test_writer_cannot_add_site_categories(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();

        $this->actingAs($writer)
            ->post(route('sites.categories.store', $site), [
                'name' => 'NP Programs',
            ])
            ->assertForbidden();
    }
}
