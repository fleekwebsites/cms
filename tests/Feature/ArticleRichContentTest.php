<?php

namespace Tests\Feature;

use App\Enums\ArticleLayout;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Author;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRichContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_save_rich_html_content_and_metadata(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $category = SiteCategory::factory()->for($site)->create(['name' => 'NP Programs']);
        $author = Author::factory()->for($site)->create([
            'name' => 'Elena Marsh',
            'credentials' => 'DNP, FNP-BC',
            'bio' => 'Elena Marsh is a family nurse practitioner and nurse educator.',
        ]);
        $content = implode('', [
            '<h2>1. What Makes a Program One of the Best?</h2>',
            '<ul><li>Accreditation</li><li>Clinical education</li></ul>',
            '<blockquote><p>A well-known university can offer an excellent program.</p></blockquote>',
            '<h3>References</h3>',
            '<ol><li>CCNE accreditation directory</li></ol>',
        ]);

        $this->actingAs($writer)
            ->post(route('articles.store'), [
                'type' => ArticleType::Blog->value,
                'title' => 'Best Nurse Practitioner Programs',
                'excerpt' => 'How to compare NP programs before making a career decision.',
                'keywords' => 'nursing, np programs',
                'site_id' => $site->id,
                'site_category_id' => $category->id,
                'author_id' => $author->id,
                'content' => $content,
                'layout' => ArticleLayout::Magazine->value,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $article = Article::query()->first();

        $this->assertNotNull($article);
        $this->assertSame($category->id, $article->site_category_id);
        $this->assertSame('Elena Marsh · DNP, FNP-BC', $article->displayAuthorLine());
        $this->assertGreaterThan(0, $article->readingTimeMinutes());
        $this->assertStringContainsString('<blockquote>', $article->content);
        $this->assertStringContainsString('<ol><li>CCNE accreditation directory</li></ol>', $article->content);
    }

    public function test_writer_can_save_text_color_and_highlight_styles(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $category = SiteCategory::factory()->for($site)->create();
        $author = Author::factory()->for($site)->create();

        $this->actingAs($writer)
            ->post(route('articles.store'), [
                'type' => ArticleType::Blog->value,
                'title' => 'Styled nursing notes',
                'content' => '<p><span style="color: #e74c3c;">Red alert</span> and <span style="background-color: #ffff00;">highlighted term</span></p>',
                'layout' => ArticleLayout::Default->value,
                'status' => 'draft',
                'site_id' => $site->id,
                'site_category_id' => $category->id,
                'author_id' => $author->id,
            ])
            ->assertRedirect();

        $article = Article::query()->first();

        $this->assertNotNull($article);
        $this->assertStringContainsString('color: #e74c3c', $article->content);
        $this->assertStringContainsString('background-color: #ffff00', $article->content);

        $this->actingAs($writer)
            ->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('color: #e74c3c', false)
            ->assertSee('background-color: #ffff00', false)
            ->assertSee('Red alert')
            ->assertSee('highlighted term');
    }

    public function test_writer_can_save_font_size_and_quill_style_classes(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();
        $category = SiteCategory::factory()->for($site)->create();
        $author = Author::factory()->for($site)->create();

        $this->actingAs($writer)
            ->post(route('articles.store'), [
                'type' => ArticleType::Blog->value,
                'title' => 'Large serif notes',
                'content' => '<p><span class="ql-size-large ql-font-serif" style="color: #0066cc;">Large blue serif</span></p>',
                'layout' => ArticleLayout::Default->value,
                'status' => 'draft',
                'site_id' => $site->id,
                'site_category_id' => $category->id,
                'author_id' => $author->id,
            ])
            ->assertRedirect();

        $article = Article::query()->first();

        $this->assertNotNull($article);
        $this->assertStringContainsString('font-size:1.5em', $article->content);
        $this->assertStringContainsString('font-family:Georgia, Times New Roman, serif', $article->content);
        $this->assertStringContainsString('color: #0066cc', $article->content);
        $this->assertStringNotContainsString('ql-size-large', $article->content);
        $this->assertStringNotContainsString('ql-font-serif', $article->content);
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
