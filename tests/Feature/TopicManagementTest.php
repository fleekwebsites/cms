<?php

namespace Tests\Feature;

use App\Enums\RemoteResource;
use App\Models\RemoteIdMapping;
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
            'https://remote.test/api/cms/content/topics*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response([
                        'status' => 'accepted',
                        'id' => 8,
                        'client_id' => $request['id'],
                        'site_category_id' => 1,
                        'name' => $request['name'],
                    ], 201);
                }

                return Http::response([
                    ['id' => 8, 'name' => 'Exam prep', 'site_category_id' => 1],
                ], 200);
            },
        ]);

        $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertCreated()
            ->assertJsonPath('id', 8)
            ->assertJsonFragment(['name' => 'Exam prep']);

        $this->assertDatabaseMissing('topics', [
            'name' => 'Exam prep',
        ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/topics')
            && $request['name'] === 'Exam prep'
            && $request['site_category_id'] === 1);
    }

    public function test_topic_create_infers_remote_id_when_response_is_minimal(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/topics*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['status' => 'accepted'], 201);
                }

                return Http::response([
                    ['id' => 8, 'name' => 'Exam prep', 'site_category_id' => 1],
                ], 200);
            },
        ]);

        $response = $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertCreated();

        $this->assertSame(8, $response->json('id'));
        $this->assertDatabaseHas('remote_id_mappings', [
            'site_id' => $site->id,
            'resource' => 'topics',
            'remote_id' => 8,
        ]);
    }

    public function test_article_accepts_a_newly_added_topic(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/topics*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['status' => 'accepted'], 201);
                }

                return Http::response([
                    ['id' => 8, 'name' => 'Exam prep', 'site_category_id' => 1],
                ], 200);
            },
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $topicResponse = $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertCreated();

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => 'blog',
                'title' => 'Topic article',
                'content' => '<p>Hello</p>',
                'layout' => 'default',
                'status' => 'draft',
                'site_category_id' => 1,
                'topic_id' => $topicResponse->json('id'),
                'author_id' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_article_accepts_mapped_client_topic_id(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        RemoteIdMapping::factory()->for($site)->create([
            'resource' => RemoteResource::Topics,
            'client_id' => 456_789,
            'remote_id' => 8,
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/topics*' => Http::response([
                ['id' => 8, 'name' => 'Exam prep', 'site_category_id' => 1],
            ], 200),
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => 'blog',
                'title' => 'Mapped topic article',
                'content' => '<p>Hello</p>',
                'layout' => 'default',
                'status' => 'draft',
                'site_category_id' => 1,
                'topic_id' => 456_789,
                'author_id' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_adding_duplicate_topic_name_returns_existing_remote_id(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $topics = [];

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/topics*' => function ($request) use (&$topics) {
                if ($request->method() === 'POST') {
                    $name = is_string($request['name'] ?? null) ? trim($request['name']) : '';
                    $categoryId = (int) ($request['site_category_id'] ?? 0);
                    $clientId = (int) ($request['id'] ?? 0);

                    foreach ($topics as $topic) {
                        if (strcasecmp($topic['name'], $name) === 0 && $topic['site_category_id'] === $categoryId) {
                            return Http::response([
                                'status' => 'accepted',
                                'id' => $topic['id'],
                                'client_id' => $clientId,
                                'site_category_id' => $categoryId,
                                'name' => $topic['name'],
                            ], 201);
                        }
                    }

                    $topics[] = [
                        'id' => count($topics) + 1,
                        'name' => $name,
                        'site_category_id' => $categoryId,
                    ];

                    return Http::response([
                        'status' => 'accepted',
                        'id' => count($topics),
                        'client_id' => $clientId,
                        'site_category_id' => $categoryId,
                        'name' => $name,
                    ], 201);
                }

                return Http::response($topics, 200);
            },
        ]);

        $first = $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertCreated();

        $second = $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertNotSame($first->json('client_id'), $second->json('client_id'));
    }

    public function test_topic_create_fails_when_remote_topic_cannot_be_confirmed(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/topics*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['status' => 'accepted'], 201);
                }

                return Http::response([], 200);
            },
        ]);

        $this->actingAs($writer)
            ->postJson(route('sites.topics.store', $site), [
                'site_category_id' => 1,
                'name' => 'Exam prep',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The topic could not be confirmed on the remote site. Check POST and GET https://remote.test/api/cms/content/topics/ The remote site accepted the request, but did not return a topic id or list the new topic for this category.');
    }

    public function test_topics_are_scoped_to_the_selected_category(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/topics*' => Http::response([
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
