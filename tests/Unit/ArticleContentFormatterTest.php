<?php

namespace Tests\Unit;

use App\Support\ArticleContentFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleContentFormatterTest extends TestCase
{
    #[Test]
    public function it_converts_quill_alignment_classes_to_inline_styles(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $formatted = $formatter->forPublish('<p class="ql-align-center">Centered</p>');

        $this->assertStringContainsString('text-align:center', $formatted);
        $this->assertStringNotContainsString('ql-align-center', $formatted);
    }

    #[Test]
    public function it_converts_quill_indent_classes_to_inline_styles(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $formatted = $formatter->forPublish('<p class="ql-indent-2">Indented</p>');

        $this->assertStringContainsString('padding-left:6em', $formatted);
        $this->assertStringNotContainsString('ql-indent-2', $formatted);
    }

    #[Test]
    public function it_converts_quill_bullet_lists_to_unordered_lists(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<ol><li data-list="bullet">First item</li><li data-list="bullet">Second item</li></ol>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<ul>', $formatted);
        $this->assertStringContainsString('<li>First item</li>', $formatted);
        $this->assertStringContainsString('<li>Second item</li>', $formatted);
        $this->assertStringNotContainsString('data-list', $formatted);
        $this->assertStringNotContainsString('<ol>', $formatted);
    }

    #[Test]
    public function it_converts_quill_ordered_lists_to_ordered_lists(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<ol><li data-list="ordered">First step</li><li data-list="ordered">Second step</li></ol>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<ol>', $formatted);
        $this->assertStringContainsString('<li>First step</li>', $formatted);
        $this->assertStringNotContainsString('data-list', $formatted);
    }

    #[Test]
    public function it_converts_nested_quill_lists_with_proper_nesting(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<ol>'
            .'<li data-list="bullet">First item</li>'
            .'<li data-list="bullet">Second item</li>'
            .'<li data-list="bullet" class="ql-indent-1">Nested item 1</li>'
            .'<li data-list="bullet" class="ql-indent-1">Nested item 2</li>'
            .'<li data-list="bullet" class="ql-indent-2">Deeply nested item</li>'
            .'<li data-list="bullet">Third item</li>'
            .'</ol>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<ul><li>First item</li><li>Second item<ul>', $formatted);
        $this->assertStringContainsString('<li>Nested item 1</li><li>Nested item 2<ul><li>Deeply nested item</li></ul></li></ul></li>', $formatted);
        $this->assertStringContainsString('<li>Third item</li></ul>', $formatted);
        $this->assertStringNotContainsString('data-list', $formatted);
    }

    #[Test]
    public function it_preserves_embedded_media_and_table_html(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
            .'<audio controls><source src="sample-audio.mp3" type="audio/mpeg"></audio>'
            .'<table><thead><tr><th>Name</th></tr></thead><tbody><tr><td>Value</td></tr></tbody></table>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ">', $formatted);
        $this->assertStringContainsString('<audio controls>', $formatted);
        $this->assertStringContainsString('<table>', $formatted);
        $this->assertStringContainsString('<th>Name</th>', $formatted);
    }

    #[Test]
    public function it_restores_escaped_embed_html_saved_as_text(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p>&lt;iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"&gt;&lt;/iframe&gt;</p>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ">', $formatted);
        $this->assertStringNotContainsString('&lt;iframe', $formatted);
    }

    #[Test]
    public function it_unwraps_quill_html_blocks(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<div class="ql-html-block" data-raw-html="&lt;iframe src=&quot;https://example.com/video&quot;&gt;&lt;/iframe&gt;"></div>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<iframe src="https://example.com/video">', $formatted);
        $this->assertStringNotContainsString('ql-html-block', $formatted);
    }

    #[Test]
    public function it_restores_escaped_inline_html_tags(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p>And here&apos;s &lt;u&gt;underlined text&lt;/u&gt; for emphasis.</p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('<u>underlined text</u>', $formatted);
        $this->assertStringNotContainsString('&lt;u&gt;', $formatted);
    }

    #[Test]
    public function it_wraps_orphan_styled_spans_and_preserves_inline_styles(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<span style="font-size: 12px;">Small text (12px)</span> | '
            .'<span style="font-size: 14px;">Regular small (14px)</span> | '
            .'<span style="font-size: 16px;">Normal text (16px)</span>'
            .'<span style="color: #e74c3c;">Red text</span> | '
            .'<span style="color: #3498db;">Blue text</span>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('<p>', $formatted);
        $this->assertStringContainsString('font-size: 12px', $formatted);
        $this->assertStringContainsString('font-size: 14px', $formatted);
        $this->assertStringContainsString('color: #e74c3c', $formatted);
        $this->assertStringContainsString('color: #3498db', $formatted);
        $this->assertStringContainsString('<span', $formatted);
    }

    #[Test]
    public function it_restores_escaped_styled_span_tags(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p>&lt;span style=&quot;font-size: 12px;&quot;&gt;Small text&lt;/span&gt; | &lt;span style=&quot;color: #e74c3c;&quot;&gt;Red text&lt;/span&gt;</p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('font-size: 12px', $formatted);
        $this->assertStringContainsString('color: #e74c3c', $formatted);
        $this->assertStringNotContainsString('&lt;span', $formatted);
    }

    #[Test]
    public function it_unwraps_quill_html_inlines(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p>Text <span class="ql-html-inline" data-raw-html="&lt;span style=&quot;color: red;&quot;&gt;Red&lt;/span&gt;">Red</span> more</p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('style="color: red"', $formatted);
        $this->assertStringContainsString('>Red</span>', $formatted);
        $this->assertStringNotContainsString('ql-html-inline', $formatted);
    }

    #[Test]
    public function it_preserves_definition_lists(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<dl><dt><strong>JavaScript</strong></dt><dd>A programming language used for web development.</dd></dl>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('<dl>', $formatted);
        $this->assertStringContainsString('<dt>', $formatted);
        $this->assertStringContainsString('<dd>', $formatted);
        $this->assertStringContainsString('<strong>JavaScript</strong>', $formatted);
    }

    #[Test]
    public function it_preserves_bold_and_italic_markup(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p><strong>Bold text</strong> and <em>italic text</em> with <b>legacy bold</b>.</p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('<strong>Bold text</strong>', $formatted);
        $this->assertStringContainsString('<em>italic text</em>', $formatted);
        $this->assertStringContainsString('<b>legacy bold</b>', $formatted);
    }

    #[Test]
    public function it_preserves_text_and_background_colors_on_inline_elements(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p><span style="color: #e74c3c;">Red text</span> and <span style="background-color: #ffff00;">Highlighted text</span></p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('color: #e74c3c', $formatted);
        $this->assertStringContainsString('background-color: #ffff00', $formatted);
    }

    #[Test]
    public function it_merges_empty_styled_spans_with_following_text_and_unwraps_export_noise(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p><span class="qwen-markdown-html"><span style="font-size: 12px;"></span><span class="qwen-markdown-text">Small text (12px)</span></span> | '
            .'<span class="qwen-markdown-html"><span style="color: #e74c3c;"></span><span class="qwen-markdown-text">Red text</span></span></p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('style="font-size: 12px"', $formatted);
        $this->assertStringContainsString('Small text (12px)</span>', $formatted);
        $this->assertStringContainsString('style="color: #e74c3c"', $formatted);
        $this->assertStringContainsString('Red text</span>', $formatted);
        $this->assertStringNotContainsString('qwen-markdown', $formatted);
    }

    #[Test]
    public function it_converts_quill_color_and_highlight_classes_to_inline_styles(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p><span class="ql-color-e60000">Red text</span> and <span class="ql-bg-ffff00">Highlighted text</span></p>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('color:#e60000', $formatted);
        $this->assertStringContainsString('background-color:#ffff00', $formatted);
        $this->assertStringNotContainsString('ql-color-', $formatted);
        $this->assertStringNotContainsString('ql-bg-', $formatted);
    }

    #[Test]
    public function it_converts_quill_size_and_font_classes_to_inline_styles(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p><span class="ql-size-large ql-font-serif">Large serif</span></p>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('font-size:1.5em', $formatted);
        $this->assertStringContainsString('font-family:Georgia, Times New Roman, serif', $formatted);
        $this->assertStringNotContainsString('ql-size-large', $formatted);
        $this->assertStringNotContainsString('ql-font-serif', $formatted);
    }

    #[Test]
    public function it_publishes_combined_text_styles_as_inline_css(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p class="ql-align-center"><strong style="color: #0066cc; background-color: #ffff00; font-size: 24px;">Styled heading</strong></p>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('text-align:center', $formatted);
        $this->assertStringContainsString('color: #0066cc', $formatted);
        $this->assertStringContainsString('background-color: #ffff00', $formatted);
        $this->assertStringContainsString('font-size: 24px', $formatted);
        $this->assertStringContainsString('<strong', $formatted);
    }

    #[Test]
    public function it_preserves_background_color_on_non_bold_text_after_unwrapping_html_inline_embeds(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p>Plain <span class="ql-html-inline" data-raw-html="<span style=&quot;background-color: #ffff00;&quot;>highlighted</span>">highlighted</span> text.</p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringNotContainsString('ql-html-inline', $formatted);
        $this->assertStringContainsString('background-color: #ffff00', $formatted);
        $this->assertStringContainsString('>highlighted</span>', $formatted);
    }

    #[Test]
    public function it_preserves_inline_styles_on_tables_and_iframes(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>'
            .'<table style="border-collapse: collapse; width: 100%;"><thead><tr style="background-color: #3498db; color: white;"><th>Name</th></tr></thead><tbody><tr><td>Value</td></tr></tbody></table>';

        $formatted = $formatter->normalize($html);

        $this->assertStringContainsString('width="560"', $formatted);
        $this->assertStringContainsString('height="315"', $formatted);
        $this->assertStringContainsString('border-collapse: collapse', $formatted);
        $this->assertStringContainsString('background-color: #3498db', $formatted);
    }

    #[Test]
    public function it_normalizes_non_breaking_spaces_from_exported_html(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<p>Welcome'."\u{00A0}".'to'."\u{00A0}".'this <strong>styled</strong>'."\u{00A0}".'paragraph.</p>';

        $formatted = $formatter->normalize($html);

        $this->assertStringNotContainsString("\u{00A0}", $formatted);
        $this->assertStringContainsString('Welcome to this', $formatted);
        $this->assertStringContainsString('styled', $formatted);
    }

    #[Test]
    public function it_strips_layout_breaking_inline_styles_from_content(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<div style="white-space: nowrap; width: 1200px; overflow: hidden; display: inline-block;">'
            .'<p style="white-space: nowrap;">Welcome to this <span style="color: #e74c3c;">styled</span> paragraph.</p>'
            .'</div>';

        $formatted = $formatter->normalize($html);

        $this->assertStringNotContainsString('white-space', $formatted);
        $this->assertStringNotContainsString('nowrap', $formatted);
        $this->assertStringNotContainsString('width: 1200px', $formatted);
        $this->assertStringNotContainsString('overflow: hidden', $formatted);
        $this->assertStringNotContainsString('inline-block', $formatted);
        $this->assertStringContainsString('color: #e74c3c', $formatted);
        $this->assertStringContainsString('Welcome to this', $formatted);
    }

    #[Test]
    public function it_preserves_semantic_html_tags(): void
    {
        $formatter = app(ArticleContentFormatter::class);

        $html = '<h2>Section</h2><blockquote><p>Quote</p></blockquote><ol><li>One</li></ol>';

        $formatted = $formatter->forPublish($html);

        $this->assertStringContainsString('<h2>Section</h2>', $formatted);
        $this->assertStringContainsString('<blockquote>', $formatted);
        $this->assertStringContainsString('<ol><li>One</li></ol>', $formatted);
    }
}
