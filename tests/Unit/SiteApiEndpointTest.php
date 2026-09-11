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

        $this->assertSame('https://remote.test/api/cms/content/', $site->apiEndpointFor('articles'));
        $this->assertSame(
            'https://remote.test/api/cms/content/550e8400-e29b-41d4-a716-446655440000',
            $site->apiEndpointFor('articles/550e8400-e29b-41d4-a716-446655440000'),
        );
        $this->assertSame('https://remote.test/api/cms/authors', $site->apiEndpointFor('authors'));
        $this->assertSame('https://remote.test/api/cms/categories', $site->apiEndpointFor('categories'));
    }

    #[Test]
    public function it_uses_api_endpoint_directly_for_articles_when_not_content_suffix(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://remote.test/api/endpoint',
        ]);

        $this->assertSame('https://remote.test/api/endpoint/', $site->apiEndpointFor('articles/'));
        $this->assertSame(
            'https://remote.test/api/endpoint/550e8400-e29b-41d4-a716-446655440000',
            $site->apiEndpointFor('articles/550e8400-e29b-41d4-a716-446655440000'),
        );
        $this->assertSame('https://remote.test/api/endpoint/authors', $site->apiEndpointFor('authors'));
    }

    #[Test]
    public function it_extracts_remote_origin_from_api_endpoint(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://nurselytic.com/api/cms/content',
        ]);

        $this->assertSame('https://nurselytic.com', $site->remoteOrigin());
    }
}
