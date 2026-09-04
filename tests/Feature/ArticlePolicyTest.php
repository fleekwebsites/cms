<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_view_update_and_publish_their_own_article(): void
    {
        $writer = User::factory()->writer()->create();
        $article = Article::factory()->for($writer)->create();

        $this->actingAs($writer);

        $this->assertTrue($writer->can('view', $article));
        $this->assertTrue($writer->can('update', $article));
        $this->assertTrue($writer->can('publish', $article));
        $this->assertFalse($writer->can('delete', $article));
    }

    public function test_writer_cannot_access_another_users_article(): void
    {
        $writer = User::factory()->writer()->create();
        $otherArticle = Article::factory()->create();

        $this->actingAs($writer);

        $this->assertFalse($writer->can('view', $otherArticle));
        $this->assertFalse($writer->can('update', $otherArticle));
    }

    public function test_admin_can_manage_any_article(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('view', $article));
        $this->assertTrue($admin->can('update', $article));
        $this->assertTrue($admin->can('delete', $article));
    }

    public function test_writer_receives_404_for_another_users_article_route(): void
    {
        $writer = User::factory()->writer()->create();
        $otherArticle = Article::factory()->create();

        $this->actingAs($writer)
            ->get(route('articles.show', $otherArticle))
            ->assertNotFound();
    }
}
