<?php

namespace Tests;

use App\Models\Site;
use App\Models\SiteDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function grantSiteAccess(User $user, Site $site, array $attributes = []): SiteDelegation
    {
        return SiteDelegation::factory()->for($user)->for($site)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    protected function fakeRemoteSite(Site $site, array $responses = []): void
    {
        Http::preventStrayRequests();

        $content = rtrim($site->api_endpoint, '/');

        $defaults = [
            "{$content}/categories*" => Http::response([['id' => 1, 'name' => 'General']], 200),
            "{$content}/authors*" => Http::response([['id' => 1, 'name' => 'Felix Ombui', 'credentials' => 'DNP']], 200),
            "{$content}/topics*" => Http::response([['id' => 1, 'name' => 'Study Tips', 'site_category_id' => 1]], 200),
            "{$content}*" => Http::response([], 200),
        ];

        Http::fake([...$defaults, ...$responses]);
    }
}
