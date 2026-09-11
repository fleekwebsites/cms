<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Support\RemoteMediaUrlResolver;
use App\Support\RemoteRecord;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoteMediaUrlResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_root_relative_urls_using_the_site_api_endpoint_origin(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://nurselytic.com/api/cms/content',
        ]);
        $resolver = new RemoteMediaUrlResolver;

        $this->assertSame(
            'https://nurselytic.com/blogs/images/example.jpg',
            $resolver->resolve('/blogs/images/example.jpg', $site),
        );
    }

    #[Test]
    public function it_leaves_absolute_urls_unchanged(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://nurselytic.com/api/cms/content',
        ]);
        $resolver = new RemoteMediaUrlResolver;

        $this->assertSame(
            'https://cdn.example.com/photo.jpg',
            $resolver->resolve('https://cdn.example.com/photo.jpg', $site),
        );
    }

    #[Test]
    public function it_resolves_image_sources_in_article_html(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://nurselytic.com/api/cms/content',
        ]);
        $resolver = new RemoteMediaUrlResolver;

        $html = $resolver->resolveInHtml(
            '<p><img src="/blogs/images/example.jpg" alt="Example"></p>',
            $site,
        );

        $this->assertStringContainsString('src="https://nurselytic.com/blogs/images/example.jpg"', $html);
    }

    #[Test]
    public function it_resolves_featured_image_urls_on_remote_records(): void
    {
        $site = Site::factory()->make([
            'api_endpoint' => 'https://nurselytic.com/api/cms/content',
        ]);
        $resolver = new RemoteMediaUrlResolver;

        $article = $resolver->resolveArticle(
            new RemoteRecord('550e8400-e29b-41d4-a716-446655440000', [
                'uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'content' => '<img src="/blogs/images/inline.jpg">',
                'featured_image_url' => '/blogs/images/featured.jpg',
            ]),
            $site,
        );

        $this->assertSame(
            'https://nurselytic.com/blogs/images/featured.jpg',
            $article->string('featured_image_url'),
        );
        $this->assertStringContainsString('https://nurselytic.com/blogs/images/inline.jpg', $article->string('content') ?? '');
    }
}
