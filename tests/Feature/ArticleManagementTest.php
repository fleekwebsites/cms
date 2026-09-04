<?php

namespace Tests\Feature;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\AuthorName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_create_a_blog_article(): void
    {
        $writer = User::factory()->writer()->create();

        $this->actingAs($writer)
            ->post(route('articles.store'), [
                'type' => ArticleType::Blog->value,
                'title' => 'Launch announcement',
                'excerpt' => 'A short summary',
                'content' => '<p>Hello world</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'user_id' => $writer->id,
            'title' => 'Launch announcement',
            'type' => ArticleType::Blog->value,
            'status' => ArticleStatus::Draft->value,
        ]);
    }

    public function test_writer_can_create_article_with_new_pen_name(): void
    {
        $writer = User::factory()->writer()->create();

        $this->actingAs($writer)
            ->post(route('articles.store'), [
                'type' => ArticleType::Blog->value,
                'title' => 'Launch announcement',
                'content' => '<p>Hello world</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'new_author_name' => 'Felix Ombui',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('author_names', [
            'user_id' => $writer->id,
            'name' => 'Felix Ombui',
        ]);

        $article = Article::query()->first();

        $this->assertNotNull($article);
        $this->assertSame('Felix Ombui', $article->display_author_name);
    }

    public function test_writer_can_select_existing_pen_name(): void
    {
        $writer = User::factory()->writer()->create();
        $penName = AuthorName::factory()->for($writer)->create(['name' => 'Editorial Team']);

        $this->actingAs($writer)
            ->post(route('articles.store'), [
                'type' => ArticleType::Faq->value,
                'title' => 'FAQ item',
                'content' => '<p>Answer</p>',
                'layout' => ArticleLayout::Default->value,
                'status' => ArticleStatus::Draft->value,
                'author_name_id' => $penName->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'author_name_id' => $penName->id,
        ]);
    }

    public function test_writer_only_sees_their_articles_in_index(): void
    {
        $writer = User::factory()->writer()->create();
        $ownArticle = Article::factory()->for($writer)->create(['title' => 'Mine']);
        Article::factory()->create(['title' => 'Someone else']);

        $response = $this->actingAs($writer)->get(route('articles.index'));

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Someone else');
    }

    public function test_admin_sees_all_articles_in_index(): void
    {
        $admin = User::factory()->admin()->create();
        Article::factory()->create(['title' => 'Writer article']);

        $this->actingAs($admin)
            ->get(route('articles.index'))
            ->assertOk()
            ->assertSee('Writer article');
    }

    public function test_writer_cannot_delete_an_article(): void
    {
        $writer = User::factory()->writer()->create();
        $article = Article::factory()->for($writer)->create();

        $this->actingAs($writer)
            ->delete(route('articles.destroy', $article))
            ->assertForbidden();
    }

    public function test_admin_can_delete_an_article(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create();

        $this->actingAs($admin)
            ->delete(route('articles.destroy', $article))
            ->assertRedirect(route('articles.index'));

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }
}
