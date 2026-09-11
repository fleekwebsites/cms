<?php

namespace Tests\Feature;

use App\Enums\RemoteResource;
use App\Models\RemoteIdMapping;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemoteIdMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_create_stores_remote_id_mapping_from_response(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/categories*' => function ($request) {
                return Http::response([
                    'status' => 'accepted',
                    'id' => 7,
                    'client_id' => $request['id'],
                    'name' => $request['name'],
                ], 201);
            },
        ]);

        $this->actingAs($admin)
            ->postJson(route('sites.categories.store', $site), [
                'name' => 'NP Programs',
            ])
            ->assertCreated()
            ->assertJsonPath('id', 7)
            ->assertJsonPath('name', 'NP Programs');

        $mapping = RemoteIdMapping::query()->first();

        $this->assertNotNull($mapping);
        $this->assertSame(RemoteResource::Categories, $mapping->resource);
        $this->assertSame(7, $mapping->remote_id);
        $this->assertGreaterThanOrEqual(100_000, $mapping->client_id);
        $this->assertNotSame($mapping->client_id, $mapping->remote_id);
    }

    public function test_article_payload_uses_mapped_category_id_when_sending(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        RemoteIdMapping::factory()->for($site)->create([
            'resource' => RemoteResource::Categories,
            'client_id' => 456_789,
            'remote_id' => 12,
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/categories*' => Http::response([
                ['id' => 12, 'name' => 'Mapped category'],
            ], 200),
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'title' => 'Mapped category article',
                'type' => 'faq',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'site_category_id' => 456_789,
                'author_id' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/content')
            && $request->method() === 'POST'
            && (int) ($request['site_category_id'] ?? 0) === 12);
    }
}
