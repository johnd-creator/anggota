<?php

namespace Tests\Feature;

use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterTemplateRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test template render endpoint returns rendered content.
     */
    public function test_template_render_returns_rendered_content(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Unit Test', 'code' => 'UT']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'name' => 'Test User',
        ]);

        $category = LetterCategory::create([
            'name' => 'Test Category',
            'code' => 'TST',
            'is_active' => true,
            'template_subject' => 'Undangan dari {{unit_name}}',
            'template_body' => 'Tanggal: {{today}}. Pembuat: {{creator_name}}.',
            'template_cc_text' => 'Arsip',
            'default_confidentiality' => 'terbatas',
            'default_urgency' => 'segera',
            'default_signer_type' => 'ketua',
        ]);

        $response = $this->actingAs($user)->get("/letters/template-render?category_id={$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'subject' => 'Undangan dari Unit Test',
            'cc_text' => 'Arsip',
            'defaults' => [
                'confidentiality' => 'terbatas',
                'urgency' => 'segera',
                'signer_type' => 'ketua',
            ],
            'has_template' => true,
        ]);

        // Body should contain rendered placeholders
        $body = $response->json('body');
        $this->assertStringContainsString('Pembuat: Test User', $body);
        $this->assertStringContainsString('Tanggal:', $body);
    }

    /**
     * Test template render requires authentication.
     */
    public function test_template_render_requires_authentication(): void
    {
        $category = LetterCategory::create([
            'name' => 'Test',
            'code' => 'TST',
            'is_active' => true,
        ]);

        $response = $this->get("/letters/template-render?category_id={$category->id}");

        $response->assertStatus(302); // Redirect to login
    }

    /**
     * Test template render validates category_id.
     */
    public function test_template_render_validates_category_id(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        $response = $this->actingAs($user)->get('/letters/template-render?category_id=99999');

        $response->assertStatus(302); // Validation redirect
    }

    /**
     * Test template render returns empty for category without template.
     */
    public function test_template_render_returns_empty_for_no_template(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        $category = LetterCategory::create([
            'name' => 'No Template',
            'code' => 'NOT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get("/letters/template-render?category_id={$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'subject' => '',
            'body' => '',
            'cc_text' => '',
            'has_template' => false,
        ]);
    }
}
