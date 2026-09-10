<?php

namespace Tests\Unit;

use App\Models\Site;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteApiEndpointTest extends TestCase
{
    #[Test]
    public function it_builds_resource_endpoints_from_content_endpoint(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);

        $this->assertSame('https://remote.test/api/cms/authors', $site->apiEndpointFor('authors'));
        $this->assertSame('https://remote.test/api/cms/categories', $site->apiEndpointFor('categories'));
    }
}
