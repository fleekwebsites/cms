<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
