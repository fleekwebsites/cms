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

    public function test_unknown_documentation_page_returns_not_found(): void
    {
        $this->get(route('documentation.show', 'missing-page'))
            ->assertNotFound();
    }
}
