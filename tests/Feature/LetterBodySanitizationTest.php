<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterBodySanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected OrganizationUnit $unit;
    protected LetterCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin_unit role and user
        $role = Role::firstOrCreate(
            ['name' => 'admin_unit'],
            ['label' => 'Admin Unit']
        );
        $this->unit = OrganizationUnit::factory()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create active letter category
        $this->category = LetterCategory::firstOrCreate(
            ['code' => 'UND'],
            ['name' => 'Undangan', 'is_active' => true]
        );
    }

    public function test_script_tags_are_stripped_on_create(): void
    {
        $dirtyBody = '<p>Hello</p><script>alert("XSS")</script><p>World</p>';

        $response = $this->actingAs($this->user)->post('/letters', [
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'admin_pusat',
            'subject' => 'Test Subject',
            'body' => $dirtyBody,
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
        ]);

        $response->assertRedirect();

        $letter = Letter::latest()->first();
        $this->assertNotNull($letter);
        $this->assertStringNotContainsString('<script>', $letter->body);
        $this->assertStringNotContainsString('alert', $letter->body);
        $this->assertStringContainsString('Hello', $letter->body);
        $this->assertStringContainsString('World', $letter->body);
    }

    public function test_javascript_links_are_sanitized_on_update(): void
    {
        $letter = Letter::factory()->create([
            'creator_user_id' => $this->user->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'status' => 'draft',
            'body' => '<p>Original</p>',
        ]);

        $dirtyBody = '<p><a href="javascript:alert(1)">Click me</a></p>';

        $response = $this->actingAs($this->user)->put("/letters/{$letter->id}", [
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'admin_pusat',
            'subject' => 'Updated Subject',
            'body' => $dirtyBody,
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
        ]);

        $response->assertRedirect();

        $letter->refresh();
        $this->assertStringNotContainsString('javascript:', $letter->body);
        $this->assertStringContainsString('Click me', $letter->body);
    }

    public function test_lists_are_preserved_on_create(): void
    {
        $bodyWithList = '<p>Items:</p><ul><li>Item 1</li><li>Item 2</li></ul><ol><li>First</li><li>Second</li></ol>';

        $response = $this->actingAs($this->user)->post('/letters', [
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'admin_pusat',
            'subject' => 'List Test',
            'body' => $bodyWithList,
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
        ]);

        $response->assertRedirect();

        $letter = Letter::latest()->first();
        $this->assertNotNull($letter);
        $this->assertStringContainsString('<ul>', $letter->body);
        $this->assertStringContainsString('<li>Item 1</li>', $letter->body);
        $this->assertStringContainsString('<ol>', $letter->body);
    }

    public function test_safe_formatting_tags_are_preserved(): void
    {
        $bodyWithFormatting = '<p><strong>Bold</strong> and <em>italic</em> and <u>underline</u></p>';

        $response = $this->actingAs($this->user)->post('/letters', [
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'admin_pusat',
            'subject' => 'Formatting Test',
            'body' => $bodyWithFormatting,
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
        ]);

        $response->assertRedirect();

        $letter = Letter::latest()->first();
        $this->assertNotNull($letter);
        $this->assertStringContainsString('<strong>Bold</strong>', $letter->body);
        $this->assertStringContainsString('<em>italic</em>', $letter->body);
        $this->assertStringContainsString('<u>underline</u>', $letter->body);
    }

    public function test_event_handlers_are_stripped(): void
    {
        $dirtyBody = '<p onclick="evil()" onmouseover="bad()">Text</p>';

        $response = $this->actingAs($this->user)->post('/letters', [
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'admin_pusat',
            'subject' => 'Event Handler Test',
            'body' => $dirtyBody,
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
        ]);

        $response->assertRedirect();

        $letter = Letter::latest()->first();
        $this->assertNotNull($letter);
        $this->assertStringNotContainsString('onclick', $letter->body);
        $this->assertStringNotContainsString('onmouseover', $letter->body);
        $this->assertStringContainsString('Text', $letter->body);
    }

    public function test_safe_links_are_preserved_with_security_attributes(): void
    {
        $bodyWithLink = '<p><a href="https://example.com">External Link</a></p>';

        $response = $this->actingAs($this->user)->post('/letters', [
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'admin_pusat',
            'subject' => 'Link Test',
            'body' => $bodyWithLink,
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
        ]);

        $response->assertRedirect();

        $letter = Letter::latest()->first();
        $this->assertNotNull($letter);
        $this->assertStringContainsString('href="https://example.com"', $letter->body);
        $this->assertStringContainsString('target="_blank"', $letter->body);
        $this->assertStringContainsString('noopener', $letter->body);
    }
}
