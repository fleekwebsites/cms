<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_author_on_remote_site(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'name' => 'Nursing Elites',
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/authors*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.authors.store', $site), [
                'name' => 'Elena Marsh',
                'credentials' => 'DNP, FNP-BC',
                'bio' => 'Family nurse practitioner and educator.',
            ])
            ->assertRedirect(route('sites.authors.index', $site));

        $this->assertDatabaseMissing('authors', [
            'name' => 'Elena Marsh',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/authors')
            && $request['name'] === 'Elena Marsh'
            && $request['credentials'] === 'DNP, FNP-BC');
    }

    public function test_admin_can_create_author_with_profile_photo_and_experience(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/authors*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.authors.store', $site), [
                'name' => 'Elena Marsh',
                'credentials' => 'DNP, FNP-BC',
                'bio' => 'Family nurse practitioner and educator.',
                'years_of_experience' => 12,
                'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            ])
            ->assertRedirect(route('sites.authors.index', $site));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/authors')
            && $request['years_of_experience'] === 12
            && isset($request['profile_photo_base64']));
    }

    public function test_admin_can_update_author_profile_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/authors/7' => Http::response([
                'id' => 7,
                'name' => 'Elena Marsh',
                'profile_photo_url' => '/authors/photos/7/profile.jpg',
            ], 200),
            'https://remote.test/api/cms/authors*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->put(route('sites.authors.update', [$site, 7]), [
                'name' => 'Elena Marsh',
                'years_of_experience' => 15,
                'profile_photo' => UploadedFile::fake()->image('updated.jpg'),
            ])
            ->assertRedirect(route('sites.authors.index', $site));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request['id'] === 7
            && isset($request['profile_photo_base64'])
            && $request['years_of_experience'] === 15);
    }

    public function test_admin_can_edit_author_when_single_author_get_is_unavailable(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://remote.test/api/cms/authors/7' => Http::response([], 404),
            'https://remote.test/api/cms/authors*' => Http::response([
                ['id' => 7, 'name' => 'Elena Marsh', 'credentials' => 'DNP, FNP-BC'],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->get(route('sites.authors.edit', [$site, 7]))
            ->assertOk()
            ->assertSee('Edit author')
            ->assertSee('Elena Marsh');
    }

    public function test_legacy_author_edit_path_redirects_to_edit_route(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://remote.test/api/cms/authors/7' => Http::response([
                'id' => 7,
                'name' => 'Elena Marsh',
                'credentials' => 'DNP, FNP-BC',
            ], 200),
        ]);

        $this->actingAs($admin)
            ->get("/sites/{$site->id}/authors/7/edit")
            ->assertRedirect(route('sites.authors.edit', [$site, 7]));
    }

    public function test_writer_cannot_manage_authors(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $this->grantSiteAccess($writer, $site);

        $this->actingAs($writer)
            ->get(route('sites.authors.create', $site))
            ->assertForbidden();
    }

    public function test_writer_can_fetch_authors_for_a_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/authors*' => Http::response([
                ['id' => 1, 'name' => 'Elena Marsh', 'credentials' => 'DNP, FNP-BC'],
            ], 200),
        ]);

        $this->actingAs($writer)
            ->getJson(route('sites.authors.options', $site))
            ->assertOk()
            ->assertJsonFragment(['id' => 1, 'name' => 'Elena Marsh · DNP, FNP-BC'])
            ->assertJsonCount(1);
    }
}
