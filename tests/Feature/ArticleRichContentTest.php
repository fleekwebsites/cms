<?php

namespace Tests\Feature;

use App\Enums\ArticleLayout;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleRichContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_save_rich_html_content_and_metadata_to_remote_site(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $content = implode('', [
            '<h2>1. What Makes a Program One of the Best?</h2>',
            '<ul><li>Accreditation</li><li>Clinical education</li></ul>',
            '<blockquote><p>A well-known university can offer an excellent program.</p></blockquote>',
            '<h3>References</h3>',
            '<ol><li>CCNE accreditation directory</li></ol>',
        ]);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Blog->value,
                'title' => 'Best Nurse Practitioner Programs',
                'excerpt' => 'How to compare NP programs before making a career decision.',
                'keywords' => 'nursing, np programs',
                'site_category_id' => 1,
                'topic_id' => 1,
                'author_id' => 1,
                'content' => $content,
                'layout' => ArticleLayout::Magazine->value,
                'status' => 'draft',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $content = (string) ($request->data()['content'] ?? '');

            return str_contains($content, '<blockquote>')
                && str_contains($content, '<ol><li>CCNE accreditation directory</li></ol>')
                && ($request->data()['reading_time_minutes'] ?? 0) >= 1;
        });
    }

    public function test_writer_can_save_text_color_and_highlight_styles(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);
        $uuid = (string) Str::uuid();

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content/' => Http::response(['status' => 'accepted'], 201),
            'https://remote.test/api/cms/content/*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Blog->value,
                'title' => 'Styled nursing notes',
                'content' => '<p><span style="color: #e74c3c;">Red alert</span> and <span style="background-color: #ffff00;">highlighted term</span></p>',
                'layout' => ArticleLayout::Default->value,
                'status' => 'draft',
                'site_category_id' => 1,
                'topic_id' => 1,
                'author_id' => 1,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $content = (string) ($request->data()['content'] ?? '');

            return str_contains($content, 'color: #e74c3c')
                && str_contains($content, 'background-color: #ffff00');
        });
    }

    public function test_writer_can_save_font_size_and_quill_style_classes(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create([
            'api_endpoint' => 'https://remote.test/api/cms/content',
        ]);
        $this->grantSiteAccess($writer, $site);

        $this->fakeRemoteSite($site, [
            'https://remote.test/api/cms/content*' => Http::response(['status' => 'accepted'], 201),
        ]);

        $this->actingAs($writer)
            ->post(route('sites.articles.store', $site), [
                'type' => ArticleType::Blog->value,
                'title' => 'Large serif notes',
                'content' => '<p><span class="ql-size-large ql-font-serif" style="color: #0066cc;">Large blue serif</span></p>',
                'layout' => ArticleLayout::Default->value,
                'status' => 'draft',
                'site_category_id' => 1,
                'topic_id' => 1,
                'author_id' => 1,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $content = (string) ($request->data()['content'] ?? '');

            return str_contains($content, 'font-size:1.5em')
                && str_contains($content, 'font-family:Georgia, Times New Roman, serif')
                && ! str_contains($content, 'ql-size-large');
        });
    }

    public function test_reading_time_is_estimated_when_not_provided(): void
    {
        $article = Article::factory()->create([
            'content' => '<p>'.str_repeat('word ', 400).'</p>',
            'excerpt' => null,
            'reading_time_minutes' => null,
        ]);

        $this->assertSame(2, $article->readingTimeMinutes());
    }
}
