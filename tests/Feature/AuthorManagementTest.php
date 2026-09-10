<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_author_profiles_for_a_site(): void
    {
        Http::preventStrayRequests();

        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'name' => 'Nursing Elites',
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        Http::fake([
            'remote.test/api/cms/authors' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($admin)
            ->post(route('authors.store'), [
                'site_id' => $site->id,
                'name' => 'Elena Marsh',
                'credentials' => 'DNP, FNP-BC',
                'bio' => 'Family nurse practitioner and educator.',
            ])
            ->assertRedirect(route('authors.index'));

        $author = Author::query()->first();

        $this->assertNotNull($author);
        $this->assertDatabaseHas('authors', [
            'site_id' => $site->id,
            'name' => 'Elena Marsh',
            'credentials' => 'DNP, FNP-BC',
        ]);

        Http::assertSent(function ($request) use ($author) {
            return $request->url() === 'https://remote.test/api/cms/authors'
                && $request['id'] === $author->id
                && $request['name'] === 'Elena Marsh'
                && $request['credentials'] === 'DNP, FNP-BC'
                && $request['bio'] === 'Family nurse practitioner and educator.';
        });
    }

    public function test_writer_cannot_manage_authors(): void
    {
        $writer = User::factory()->writer()->create();

        $this->actingAs($writer)
            ->get(route('authors.create'))
            ->assertForbidden();
    }

    public function test_writer_can_fetch_authors_for_a_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $author = Author::factory()->for($site)->create([
            'name' => 'Elena Marsh',
            'credentials' => 'DNP, FNP-BC',
        ]);
        Author::factory()->create(['name' => 'Other Site Author']);

        $response = $this->actingAs($writer)
            ->getJson(route('sites.authors.index', $site))
            ->assertOk();

        $response->assertJsonFragment(['id' => $author->id, 'name' => 'Elena Marsh · DNP, FNP-BC']);
        $response->assertJsonCount(1);
    }
}
