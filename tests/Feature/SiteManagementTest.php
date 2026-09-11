<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_site_with_generated_api_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('sites.store'), [
                'name' => 'Marketing Site',
                'api_endpoint' => 'https://marketing.test/api/cms/content',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $site = Site::query()->first();

        $this->assertNotNull($site);
        $this->assertSame('Marketing Site', $site->name);
        $this->assertStringStartsWith('cms_', $site->api_key);
    }

    public function test_writer_cannot_access_site_management_pages(): void
    {
        $writer = User::factory()->writer()->create();

        $this->actingAs($writer)
            ->get(route('sites.index'))
            ->assertForbidden();
    }

    public function test_admin_can_regenerate_a_site_api_key(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create();
        $originalKey = $site->api_key;

        $this->actingAs($admin)
            ->post(route('sites.api-key.update', $site))
            ->assertRedirect(route('sites.show', $site));

        $this->assertNotSame($originalKey, $site->fresh()->api_key);
    }

    public function test_admin_can_toggle_site_status(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('sites.status.update', $site))
            ->assertRedirect();

        $this->assertFalse($site->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('sites.status.update', $site))
            ->assertRedirect();

        $this->assertTrue($site->fresh()->is_active);
    }

    public function test_admin_connection_page_shows_api_key_categories_and_authors(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create(['name' => 'Nursing Elites']);

        $this->actingAs($admin)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('API key')
            ->assertSee('Categories')
            ->assertSee('Authors')
            ->assertSee('Write article')
            ->assertSee('Open workspace');
    }

    public function test_admin_can_delete_a_site(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->delete(route('sites.destroy', $site))
            ->assertRedirect(route('sites.index'));

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }
}
