<?php

namespace Tests\Feature;

use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberAspirationsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_without_member_profile_can_open_member_aspirations_index(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->value('id'),
            'organization_unit_id' => $unit->id,
            'member_id' => null,
        ]);

        $category = AspirationCategory::create(['name' => 'Kategori Test']);

        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);

        Aspiration::create([
            'title' => 'Aspirasi Unit A',
            'body' => 'Isi aspirasi unit A',
            'member_id' => $member->id,
            'organization_unit_id' => $unit->id,
            'category_id' => $category->id,
            'status' => 'new',
            'support_count' => 0,
            'user_id' => $adminUnit->id,
        ]);

        $response = $this->actingAs($adminUnit)->get(route('member.aspirations.index'));

        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Member/Aspirations/Index')
                ->has('aspirations.data', 1)
                ->where('aspirations.data.0.is_supported', false)
                ->where('aspirations.data.0.is_own', false)
        );
    }

    public function test_admin_pusat_uses_linked_member_unit_on_member_aspirations_index(): void
    {
        $dpp = OrganizationUnit::factory()->create([
            'name' => 'DPP',
            'is_pusat' => true,
        ]);
        $unit = OrganizationUnit::factory()->create(['name' => 'Unit Asal']);
        $otherUnit = OrganizationUnit::factory()->create(['name' => 'Unit Lain']);

        $adminPusat = User::factory()->create([
            'role_id' => Role::where('name', 'admin_pusat')->value('id'),
            'organization_unit_id' => $dpp->id,
        ]);

        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);
        $adminPusat->update(['member_id' => $member->id]);

        $category = AspirationCategory::create(['name' => 'Kategori Pusat']);

        Aspiration::create([
            'title' => 'Aspirasi Unit Asal',
            'body' => 'Isi aspirasi unit asal',
            'member_id' => $member->id,
            'organization_unit_id' => $unit->id,
            'category_id' => $category->id,
            'status' => 'new',
            'support_count' => 0,
            'user_id' => $adminPusat->id,
        ]);

        Aspiration::create([
            'title' => 'Aspirasi Unit Lain',
            'body' => 'Isi aspirasi unit lain',
            'member_id' => $member->id,
            'organization_unit_id' => $otherUnit->id,
            'category_id' => $category->id,
            'status' => 'new',
            'support_count' => 0,
            'user_id' => $adminPusat->id,
        ]);

        $response = $this->actingAs($adminPusat)->get(route('member.aspirations.index'));

        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Member/Aspirations/Index')
                ->has('aspirations.data', 1)
                ->where('aspirations.data.0.title', 'Aspirasi Unit Asal')
        );
    }
}
