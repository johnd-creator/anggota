<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LetterCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $adminUnit;
    protected $anggota;
    protected $unit;
    protected $unitB;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);

        $this->unit = OrganizationUnit::factory()->create(['code' => '010']);
        $this->unitB = OrganizationUnit::factory()->create(['code' => '020']);

        $this->superAdmin = User::factory()->create([
            'role_id' => $roleSuperAdmin->id,
        ]);

        $this->adminUnit = User::factory()->create([
            'role_id' => $roleAdminUnit->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
        ]);
        $this->anggota = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $member->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->category = LetterCategory::create([
            'name' => 'Test',
            'code' => 'TST',
            'is_active' => true,
        ]);
    }

    public function test_seeder_creates_categories_with_colors()
    {
        (new LetterCategorySeeder())->run();

        $this->assertDatabaseHas('letter_categories', ['code' => 'ORG', 'color' => 'green']);
        $this->assertDatabaseHas('letter_categories', ['code' => 'AGT', 'color' => 'cyan']);
        $this->assertDatabaseHas('letter_categories', ['code' => 'HI', 'color' => 'indigo']);
        $this->assertDatabaseHas('letter_categories', ['code' => 'ADV', 'color' => 'red']);
        $this->assertDatabaseHas('letter_categories', ['code' => 'EKS', 'color' => 'amber']);
    }

    public function test_rahasia_letter_not_accessible_by_non_recipient()
    {
        // Create a rahasia letter to unit B
        $letter = Letter::create([
            'creator_user_id' => $this->superAdmin->id,
            'from_unit_id' => $this->unitB->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unitB->id,
            'subject' => 'Rahasia Letter',
            'body' => 'Secret',
            'confidentiality' => 'rahasia',
            'urgency' => 'biasa',
            'status' => 'sent',
        ]);

        // Anggota from unit A should not be able to view it
        $response = $this->actingAs($this->anggota)
            ->get(route('letters.show', $letter->id));

        $response->assertStatus(403);
    }

    public function test_rahasia_letter_accessible_by_recipient_unit_admin()
    {
        // adminUnit B can view rahasia letter sent to their unit
        $adminUnitB = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $this->unitB->id,
        ]);

        $letter = Letter::create([
            'creator_user_id' => $this->superAdmin->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unitB->id,
            'subject' => 'Rahasia Letter',
            'body' => 'Secret',
            'confidentiality' => 'rahasia',
            'urgency' => 'biasa',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($adminUnitB)
            ->get(route('letters.show', $letter->id));

        $response->assertStatus(200);
    }
}
