<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_category_to_remote_site(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/categories*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.categories.store', $site), [
                'name' => 'NP Programs',
            ])
            ->assertRedirect(route('sites.categories.index', $site));

        $this->assertDatabaseMissing('site_categories', [
            'name' => 'NP Programs',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/categories')
            && $request['name'] === 'NP Programs');
    }

    public function test_writer_can_fetch_categories_for_active_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/categories*' => Http::response([
                ['id' => 1, 'name' => 'Burnout'],
            ], 200),
        ]);

        $this->actingAs($writer)
            ->getJson(route('sites.categories.options', $site))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Burnout']);
    }

    public function test_admin_can_rename_a_category_on_remote_site(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/categories*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->put(route('sites.categories.update', [$site, 1]), [
                'name' => 'Updated programs',
            ])
            ->assertRedirect(route('sites.categories.index', $site));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/categories')
            && $request['id'] === 1
            && $request['name'] === 'Updated programs');
    }

    public function test_writer_without_write_access_cannot_add_site_categories(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();

        $this->grantSiteAccess($writer, $site, ['can_write_articles' => false]);

        $this->actingAs($writer)
            ->post(route('sites.categories.store', $site), [
                'name' => 'NP Programs',
            ])
            ->assertForbidden();
    }

    public function test_writer_can_add_a_category_from_the_editor(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/categories*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->postJson(route('sites.categories.store', $site), [
                'name' => 'Clinical rotations',
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Clinical rotations'])
            ->assertJsonStructure(['id', 'client_id']);
    }
}
