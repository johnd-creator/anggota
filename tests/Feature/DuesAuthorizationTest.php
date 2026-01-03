<?php

namespace Tests\Feature;

use App\Models\DuesPayment;
use App\Models\FinanceCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected Member $memberA;
    protected Member $memberB;
    protected User $bendaharaA;
    protected User $anggotaA;
    protected User $adminUnitA;
    protected Role $bendaharaRole;
    protected Role $anggotaRole;
    protected Role $adminUnitRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $this->unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        // Create roles
        $this->anggotaRole = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $this->bendaharaRole = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);
        $this->adminUnitRole = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);

        // Create members
        $this->memberA = Member::factory()->create([
            'organization_unit_id' => $this->unitA->id,
            'status' => 'aktif',
        ]);
        $this->memberB = Member::factory()->create([
            'organization_unit_id' => $this->unitB->id,
            'status' => 'aktif',
        ]);

        // Create users
        $this->bendaharaA = User::factory()->create([
            'role_id' => $this->bendaharaRole->id,
            'member_id' => $this->memberA->id,
            'organization_unit_id' => $this->unitA->id,
        ]);
        $this->anggotaA = User::factory()->create([
            'role_id' => $this->anggotaRole->id,
            'member_id' => $this->memberA->id,
            'organization_unit_id' => $this->unitA->id,
        ]);
        $this->adminUnitA = User::factory()->create([
            'role_id' => $this->adminUnitRole->id,
            'organization_unit_id' => $this->unitA->id,
        ]);
    }

    public function test_bendahara_can_update_member_in_same_unit(): void
    {
        $response = $this->actingAs($this->bendaharaA)->post('/finance/dues/update', [
            'member_id' => $this->memberA->id,
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dues_payments', [
            'member_id' => $this->memberA->id,
            'period' => '2026-01',
            'status' => 'paid',
        ]);
    }

    public function test_bendahara_cannot_update_member_in_different_unit(): void
    {
        $response = $this->actingAs($this->bendaharaA)->post('/finance/dues/update', [
            'member_id' => $this->memberB->id, // Different unit
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('dues_payments', [
            'member_id' => $this->memberB->id,
            'period' => '2026-01',
            'status' => 'paid',
        ]);
    }

    public function test_member_dues_page_only_shows_own_data(): void
    {
        // Create dues for both members
        DuesPayment::create([
            'member_id' => $this->memberA->id,
            'organization_unit_id' => $this->unitA->id,
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);
        DuesPayment::create([
            'member_id' => $this->memberB->id,
            'organization_unit_id' => $this->unitB->id,
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);

        $response = $this->actingAs($this->anggotaA)->get('/member/dues');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Member/Dues')
                ->where('hasMember', true)
                ->has('payments')
        );
    }

    public function test_anggota_cannot_update_dues(): void
    {
        $response = $this->actingAs($this->anggotaA)->post('/finance/dues/update', [
            'member_id' => $this->memberA->id,
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);

        $response->assertStatus(403);
    }

    public function test_audit_log_created_on_dues_update(): void
    {
        $response = $this->actingAs($this->bendaharaA)->post('/finance/dues/update', [
            'member_id' => $this->memberA->id,
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);

        $response->assertRedirect();

        // Check audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'dues.mark_paid',
            'user_id' => $this->bendaharaA->id,
        ]);
    }

    public function test_mass_update_denied_for_cross_unit_members(): void
    {
        // ... (existing code) ...
        // Create category manually instead of using factory
        $category = FinanceCategory::create([
            'organization_unit_id' => $this->unitA->id,
            'name' => 'Iuran Bulanan',
            'type' => 'income',
            'is_recurring' => true,
            'created_by' => $this->bendaharaA->id,
        ]);

        $response = $this->actingAs($this->bendaharaA)->post('/finance/dues/mass-update', [
            'member_ids' => [$this->memberA->id, $this->memberB->id], // Cross-unit
            'period' => '2026-01',
            'category_id' => $category->id,
            'amount' => 30000,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_unit_cannot_update_dues(): void
    {
        $response = $this->actingAs($this->adminUnitA)->post('/finance/dues/update', [
            'member_id' => $this->memberA->id,
            'period' => '2026-01',
            'status' => 'paid',
            'amount' => 30000,
        ]);

        $response->assertStatus(403);
    }
}
