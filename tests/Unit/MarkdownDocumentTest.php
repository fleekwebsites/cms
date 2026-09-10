<?php

namespace Tests\Unit;

use App\Support\Documentation\MarkdownDocument;
use Tests\TestCase;

class MarkdownDocumentTest extends TestCase
{
    public function test_renders_markdown_tables_as_html(): void
    {
        $html = (new MarkdownDocument)->render('README.md');

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<th', $html);
    }
}
