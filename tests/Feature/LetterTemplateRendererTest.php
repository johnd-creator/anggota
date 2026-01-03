<?php

namespace Tests\Feature;

use App\Services\LetterTemplateRenderer;
use Tests\TestCase;

class LetterTemplateRendererTest extends TestCase
{
    protected LetterTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new LetterTemplateRenderer();
    }

    /**
     * Test that known placeholders are replaced correctly.
     */
    public function test_known_placeholders_are_replaced(): void
    {
        $template = 'Kepada Yth. {{unit_name}}, Tanggal: {{today}}';
        $context = [
            'unit_name' => 'Unit Test',
            'today' => '24 Desember 2025',
        ];

        $result = $this->renderer->render($template, $context);

        $this->assertEquals('Kepada Yth. Unit Test, Tanggal: 24 Desember 2025', $result);
    }

    /**
     * Test that unknown placeholders are replaced with empty string.
     */
    public function test_unknown_placeholders_are_replaced_with_empty(): void
    {
        $template = 'Hello {{unknown_field}}, World!';

        $result = $this->renderer->render($template, []);

        $this->assertEquals('Hello , World!', $result);
    }

    /**
     * Test that dangerous content is not evaluated.
     */
    public function test_dangerous_content_not_evaluated(): void
    {
        // Attempt to inject PHP code - doesn't match {{word}} pattern
        // so it stays unchanged (safe - not executed)
        $template = '{{eval(phpinfo())}}';
        $result = $this->renderer->render($template, []);

        // The regex requires \w+ (word chars only), so this stays unchanged
        $this->assertEquals('{{eval(phpinfo())}}', $result);

        // Test actual word injection attempt
        $template2 = '{{__evil__}}';
        $result2 = $this->renderer->render($template2, ['__evil__' => 'bad']);
        // __evil__ not in whitelist, so empty
        $this->assertEquals('', $result2);
    }

    /**
     * Test that context is filtered to whitelist only.
     */
    public function test_context_filtered_to_whitelist(): void
    {
        $template = '{{unit_name}} - {{evil}}';
        $context = [
            'unit_name' => 'Safe Unit',
            'evil' => 'malicious_data',
        ];

        $result = $this->renderer->render($template, $context);

        // evil is not in whitelist, so it's replaced with empty
        $this->assertEquals('Safe Unit - ', $result);
    }

    /**
     * Test buildContext generates correct values.
     */
    public function test_build_context_generates_correct_values(): void
    {
        $data = [
            'from_unit' => [
                'name' => 'Unit ABC',
                'code' => 'UAB',
            ],
            'to_member' => [
                'full_name' => 'John Doe',
            ],
            'creator' => [
                'name' => 'Admin User',
            ],
        ];

        $context = $this->renderer->buildContext($data);

        $this->assertEquals('Unit ABC', $context['unit_name']);
        $this->assertEquals('UAB', $context['unit_code']);
        $this->assertEquals('John Doe', $context['recipient_name']);
        $this->assertEquals('Admin User', $context['creator_name']);
        $this->assertArrayHasKey('today', $context);
        $this->assertArrayHasKey('year', $context);
    }

    /**
     * Test template validation catches unknown placeholders.
     */
    public function test_validate_template_catches_unknown_placeholders(): void
    {
        $template = '{{unit_name}} and {{bad_placeholder}}';

        $errors = $this->renderer->validateTemplate($template);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('bad_placeholder', $errors[0]);
    }

    /**
     * Test template validation passes for valid template.
     */
    public function test_validate_template_passes_for_valid(): void
    {
        $template = '{{unit_name}} - {{today}} - {{letter_number}}';

        $errors = $this->renderer->validateTemplate($template);

        $this->assertCount(0, $errors);
    }

    /**
     * Test empty template returns empty string.
     */
    public function test_empty_template_returns_empty(): void
    {
        $result = $this->renderer->render('', []);

        $this->assertEquals('', $result);
    }

    /**
     * Test template without placeholders returns unchanged.
     */
    public function test_template_without_placeholders_unchanged(): void
    {
        $template = 'This is plain text without any placeholders.';

        $result = $this->renderer->render($template, []);

        $this->assertEquals($template, $result);
    }
}
