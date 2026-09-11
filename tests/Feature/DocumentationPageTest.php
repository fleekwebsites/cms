<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationPageTest extends TestCase
{
    public function test_guest_can_view_documentation_index(): void
    {
        $this->get(route('documentation.index'))
            ->assertOk()
            ->assertSee('documentation-shell', false)
            ->assertSee('API documentation', false)
            ->assertSee('Overview', false)
            ->assertSee('<table', false);
    }

    public function test_guest_can_view_documentation_page(): void
    {
        $this->get(route('documentation.show', 'authentication'))
            ->assertOk()
            ->assertSee('Authentication', false);
    }

    public function test_guest_can_view_integration_guide(): void
    {
        $this->get(route('documentation.show', 'integration-guide'))
            ->assertOk()
            ->assertSee('Integration guide', false)
            ->assertSee('Remote site receiver API', false);
    }

    public function test_guest_can_view_remote_receiver_api_without_database_schemas(): void
    {
        $response = $this->get(route('documentation.show', 'remote-site-receiver-api'));

        $response->assertOk()
            ->assertSee('Articles', false)
            ->assertSee('Topics', false)
            ->assertDontSee('CREATE TABLE', false)
            ->assertDontSee('cms_authors', false);
    }

    public function test_unknown_documentation_page_returns_not_found(): void
    {
        $this->get(route('documentation.show', 'missing-page'))
            ->assertNotFound();
    }
}
