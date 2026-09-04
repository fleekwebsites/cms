<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_unknown_route(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_to_dashboard_from_unknown_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/this-page-does-not-exist')
            ->assertRedirect(route('dashboard'));
    }

    public function test_matched_route_authorization_404_is_not_redirected(): void
    {
        $writer = User::factory()->writer()->create();
        $otherArticle = Article::factory()->create();

        $this->actingAs($writer)
            ->get(route('articles.show', $otherArticle))
            ->assertNotFound();
    }
}
