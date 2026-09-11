<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopicManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_add_a_topic_for_a_category(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/topics*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Exam prep']);

        $this->assertDatabaseMissing('topics', [
            'name' => 'Exam prep',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/topics')
            && $request['name'] === 'Exam prep'
            && $request['site_category_id'] === 1);
    }

    public function test_topics_are_scoped_to_the_selected_category(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/topics*' => Http::response([
                ['id' => 1, 'name' => 'Visible topic', 'site_category_id' => 1],
            ], 200),
        ]);

        $this->actingAs($writer)
            ->getJson(route('sites.topics.options', [$site, 1]))
            ->assertOk()
            ->assertJsonFragment(['id' => 1, 'name' => 'Visible topic'])
            ->assertJsonMissing(['name' => 'Hidden topic']);
    }
}
