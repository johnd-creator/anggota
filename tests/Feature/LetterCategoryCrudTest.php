<?php

namespace Tests\Feature;

use App\Models\LetterCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test create category with template fields.
     */
    public function test_create_category_with_template_fields(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $data = [
            'name' => 'Test Category',
            'code' => 'TST',
            'description' => 'Test description',
            'color' => 'blue',
            'sort_order' => 1,
            'is_active' => true,
            // Template fields
            'template_subject' => 'Undangan Rapat {{unit_name}}',
            'template_body' => 'Dengan hormat, bersama ini kami sampaikan...',
            'template_cc_text' => 'Arsip',
            'default_confidentiality' => 'biasa',
            'default_urgency' => 'segera',
            'default_signer_type' => 'ketua',
        ];

        $response = $this->actingAs($superAdmin)->post('/admin/letter-categories', $data);

        $response->assertRedirect('/admin/letter-categories');

        $this->assertDatabaseHas('letter_categories', [
            'name' => 'Test Category',
            'code' => 'TST',
            'template_subject' => 'Undangan Rapat {{unit_name}}',
            'template_body' => 'Dengan hormat, bersama ini kami sampaikan...',
            'template_cc_text' => 'Arsip',
            'default_confidentiality' => 'biasa',
            'default_urgency' => 'segera',
            'default_signer_type' => 'ketua',
        ]);
    }

    /**
     * Test update category with template fields.
     */
    public function test_update_category_with_template_fields(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $category = LetterCategory::create([
            'name' => 'Original',
            'code' => 'ORI',
            'color' => 'neutral',
            'is_active' => true,
        ]);

        $data = [
            'name' => 'Updated',
            'code' => 'UPD',
            'color' => 'green',
            'is_active' => true,
            'template_subject' => 'Updated Subject {{today}}',
            'template_body' => 'Updated body content',
            'template_cc_text' => 'Updated CC',
            'default_confidentiality' => 'terbatas',
            'default_urgency' => 'kilat',
            'default_signer_type' => 'sekretaris',
        ];

        $response = $this->actingAs($superAdmin)->put("/admin/letter-categories/{$category->id}", $data);

        $response->assertRedirect('/admin/letter-categories');

        $this->assertDatabaseHas('letter_categories', [
            'id' => $category->id,
            'name' => 'Updated',
            'code' => 'UPD',
            'template_subject' => 'Updated Subject {{today}}',
            'template_body' => 'Updated body content',
            'default_confidentiality' => 'terbatas',
            'default_urgency' => 'kilat',
            'default_signer_type' => 'sekretaris',
        ]);
    }

    /**
     * Test template fields are nullable.
     */
    public function test_template_fields_are_nullable(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $data = [
            'name' => 'No Template',
            'code' => 'NOT',
            'color' => 'neutral',
            'is_active' => true,
            // No template fields
        ];

        $response = $this->actingAs($superAdmin)->post('/admin/letter-categories', $data);

        $response->assertRedirect('/admin/letter-categories');

        $category = LetterCategory::where('code', 'NOT')->first();
        $this->assertNull($category->template_subject);
        $this->assertNull($category->template_body);
        $this->assertNull($category->default_confidentiality);
    }

    /**
     * Test invalid default values are rejected.
     */
    public function test_invalid_default_values_rejected(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $data = [
            'name' => 'Invalid',
            'code' => 'INV',
            'color' => 'neutral',
            'is_active' => true,
            'default_confidentiality' => 'invalid_value', // Invalid
        ];

        $response = $this->actingAs($superAdmin)->post('/admin/letter-categories', $data);

        $response->assertSessionHasErrors('default_confidentiality');
    }
}
