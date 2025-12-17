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

    protected $superAdmin;
    protected $adminUnit;
    protected $roleSuperAdmin;
    protected $roleAdminUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $this->roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);

        $this->superAdmin = User::factory()->create([
            'role_id' => $this->roleSuperAdmin->id,
        ]);

        $this->adminUnit = User::factory()->create([
            'role_id' => $this->roleAdminUnit->id,
        ]);
    }

    public function test_super_admin_can_access_letter_categories_index()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.letter-categories.index'));

        $response->assertStatus(200);
    }

    public function test_admin_unit_cannot_access_letter_categories()
    {
        $response = $this->actingAs($this->adminUnit)
            ->get(route('admin.letter-categories.index'));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_create_letter_category()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.letter-categories.create'));

        $response->assertStatus(200);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.letter-categories.store'), [
                'name' => 'Test Category',
                'code' => 'TST',
                'description' => 'Test description',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.letter-categories.index'));

        $this->assertDatabaseHas('letter_categories', [
            'code' => 'TST',
            'name' => 'Test Category',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_update_letter_category()
    {
        $category = LetterCategory::create([
            'name' => 'Original Name',
            'code' => 'ORG',
            'description' => 'Original description',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->put(route('admin.letter-categories.update', $category->id), [
                'name' => 'Updated Name',
                'code' => 'UPD',
                'description' => 'Updated description',
                'is_active' => false,
            ]);

        $response->assertRedirect(route('admin.letter-categories.index'));

        $category->refresh();
        $this->assertEquals('Updated Name', $category->name);
        $this->assertEquals('UPD', $category->code);
        $this->assertFalse($category->is_active);
    }

    public function test_letter_category_code_is_auto_uppercased()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.letter-categories.store'), [
                'name' => 'Lowercase Test',
                'code' => 'low',
                'description' => null,
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('letter_categories', [
            'code' => 'LOW',
        ]);
    }

    public function test_cannot_delete_letter_category_with_letters()
    {
        $category = LetterCategory::create([
            'name' => 'With Letters',
            'code' => 'WTL',
            'is_active' => true,
        ]);

        // Create a letter using this category
        \DB::table('letters')->insert([
            'creator_user_id' => $this->superAdmin->id,
            'letter_category_id' => $category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.letter-categories.destroy', $category->id));

        $response->assertSessionHasErrors('category');

        $this->assertDatabaseHas('letter_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_can_delete_letter_category_without_letters()
    {
        $category = LetterCategory::create([
            'name' => 'No Letters',
            'code' => 'NLT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.letter-categories.destroy', $category->id));

        $response->assertRedirect(route('admin.letter-categories.index'));

        $this->assertDatabaseMissing('letter_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_code_must_be_unique()
    {
        LetterCategory::create([
            'name' => 'First Category',
            'code' => 'DUP',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.letter-categories.store'), [
                'name' => 'Second Category',
                'code' => 'DUP',
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('code');
    }
}
