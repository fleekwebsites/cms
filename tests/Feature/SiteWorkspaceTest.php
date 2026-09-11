<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_delegated_sites_for_writers(): void
    {
        $writer = User::factory()->writer()->create();
        $assigned = Site::factory()->create(['name' => 'Assigned Site']);
        Site::factory()->create(['name' => 'Other Site']);
        $this->grantSiteAccess($writer, $assigned);

        $this->actingAs($writer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Assigned Site')
            ->assertDontSee('Other Site');
    }

    public function test_writer_cannot_open_a_site_they_are_not_delegated_to(): void
    {
        $writer = User::factory()->writer()->create();
        $site = Site::factory()->create();

        $this->actingAs($writer)
            ->get(route('sites.articles.index', $site))
            ->assertForbidden();
    }

    public function test_admin_can_assign_site_delegations_when_creating_a_writer(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Delegated Writer',
                'email' => 'delegated@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => Role::Writer->value,
                'delegations' => [
                    $site->id => [
                        'enabled' => '1',
                        'can_write_articles' => '1',
                        'can_manage_authors' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('users.index'));

        $writer = User::query()->where('email', 'delegated@example.com')->first();

        $this->assertNotNull($writer);
        $this->assertDatabaseHas('site_delegations', [
            'user_id' => $writer->id,
            'site_id' => $site->id,
            'can_write_articles' => 1,
            'can_manage_authors' => 1,
            'can_manage_categories' => 0,
        ]);
    }
}
