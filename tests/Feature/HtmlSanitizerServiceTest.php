<?php

namespace Tests\Feature;

use App\Services\HtmlSanitizerService;
use Tests\TestCase;

class HtmlSanitizerServiceTest extends TestCase
{
    protected HtmlSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizerService();
    }

    public function test_script_tags_are_stripped(): void
    {
        $dirty = '<p>Hello</p><script>alert("XSS")</script><p>World</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringContainsString('<p>World</p>', $clean);
    }

    public function test_javascript_links_are_stripped(): void
    {
        $dirty = '<p><a href="javascript:alert(1)">Click me</a></p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('javascript:', $clean);
        // The anchor should be stripped or cleaned
        $this->assertStringContainsString('Click me', $clean);
    }

    public function test_event_handlers_are_stripped(): void
    {
        $dirty = '<p onclick="evil()" onmouseover="bad()">Text</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('Text', $clean);
    }

    public function test_allowed_tags_are_preserved(): void
    {
        $dirty = '<p><strong>Bold</strong> <em>italic</em> <u>underline</u></p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('<strong>Bold</strong>', $clean);
        $this->assertStringContainsString('<em>italic</em>', $clean);
        $this->assertStringContainsString('<u>underline</u>', $clean);
    }

    public function test_lists_are_preserved(): void
    {
        $dirty = '<ul><li>Item 1</li><li>Item 2</li></ul>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>Item 1</li>', $clean);
        $this->assertStringContainsString('<li>Item 2</li>', $clean);
    }

    public function test_text_align_style_preserved(): void
    {
        $dirty = '<p style="text-align: center">Centered</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('text-align: center', $clean);
        $this->assertStringContainsString('<p', $clean);
    }

    public function test_invalid_styles_are_stripped(): void
    {
        $dirty = '<p style="background: url(evil); color: red; text-align: left">Text</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('background', $clean);
        $this->assertStringNotContainsString('color', $clean);
        $this->assertStringContainsString('text-align: left', $clean);
    }

    public function test_safe_links_are_preserved(): void
    {
        $dirty = '<p><a href="https://example.com">Link</a></p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_mailto_links_are_preserved(): void
    {
        $dirty = '<p><a href="mailto:test@example.com">Email</a></p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('href="mailto:test@example.com"', $clean);
    }

    public function test_iframe_tags_are_stripped(): void
    {
        $dirty = '<p>Before</p><iframe src="evil.html"></iframe><p>After</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('Before', $clean);
        $this->assertStringContainsString('After', $clean);
    }

    public function test_data_uri_links_are_stripped(): void
    {
        $dirty = '<p><a href="data:text/html,<script>alert(1)</script>">Click</a></p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('data:', $clean);
    }

    public function test_plain_text_converted_to_html(): void
    {
        $text = "Line one\n\nLine two\nLine three";
        $html = $this->sanitizer->toSafeHtml($text);

        $this->assertStringContainsString('<p>Line one</p>', $html);
        $this->assertStringContainsString('<p>Line two<br>Line three</p>', $html);
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertEquals('', $this->sanitizer->sanitize(''));
        $this->assertEquals('', $this->sanitizer->sanitize(null));
    }

    public function test_headings_are_preserved(): void
    {
        $dirty = '<h2>Title</h2><h3>Subtitle</h3><p>Content</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<h3>Subtitle</h3>', $clean);
    }

    public function test_nested_dangerous_content_stripped(): void
    {
        $dirty = '<p><span><script>evil()</script>Safe text</span></p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('evil', $clean);
        $this->assertStringContainsString('Safe text', $clean);
    }
}
