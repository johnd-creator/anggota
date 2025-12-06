<?php

namespace Tests\Feature;

use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DuesPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $bendahara;
    protected $unit;
    protected $otherUnit;
    protected $roleSuperAdmin;
    protected $roleBendahara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $this->roleBendahara = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);
        Role::create(['name' => 'reguler', 'label' => 'Reguler']);

        $this->unit = OrganizationUnit::factory()->create();
        $this->otherUnit = OrganizationUnit::factory()->create();

        $this->superAdmin = User::factory()->create([
            'role_id' => $this->roleSuperAdmin->id,
        ]);

        $this->bendahara = User::factory()->create([
            'role_id' => $this->roleBendahara->id,
            'organization_unit_id' => $this->unit->id,
        ]);
    }

    public function test_bendahara_can_view_dues_for_own_unit()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->bendahara)
            ->get(route('finance.dues.index'));

        $response->assertOk();
        $response->assertInertia(
            fn(AssertableInertia $page) => $page
                ->component('Finance/Dues/Index')
                ->has('members.data', 1)
        );
    }

    public function test_bendahara_cannot_see_other_unit_members()
    {
        Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        Member::factory()->create([
            'organization_unit_id' => $this->otherUnit->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->bendahara)
            ->get(route('finance.dues.index'));

        $response->assertOk();
        $response->assertInertia(
            fn(AssertableInertia $page) => $page
                ->component('Finance/Dues/Index')
                ->has('members.data', 1) // Only own unit member
        );
    }

    public function test_super_admin_can_view_all_units()
    {
        Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        Member::factory()->create([
            'organization_unit_id' => $this->otherUnit->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('finance.dues.index'));

        $response->assertOk();
        $response->assertInertia(
            fn(AssertableInertia $page) => $page
                ->component('Finance/Dues/Index')
                ->has('members.data', 2) // All unit members
        );
    }

    public function test_bendahara_can_mark_member_as_paid()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $period = now()->format('Y-m');

        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.dues.update'), [
                'member_id' => $member->id,
                'period' => $period,
                'status' => 'paid',
                'amount' => 50000,
                'notes' => 'Paid via transfer',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('dues_payments', [
            'member_id' => $member->id,
            'period' => $period,
            'status' => 'paid',
            'amount' => 50000,
            'recorded_by' => $this->bendahara->id,
        ]);
    }

    public function test_bendahara_cannot_update_other_unit_member()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->otherUnit->id,
            'status' => 'aktif',
        ]);

        $period = now()->format('Y-m');

        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.dues.update'), [
                'member_id' => $member->id,
                'period' => $period,
                'status' => 'paid',
                'amount' => 50000,
            ]);

        $response->assertForbidden();
    }

    public function test_amount_required_when_marking_as_paid()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $period = now()->format('Y-m');

        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.dues.update'), [
                'member_id' => $member->id,
                'period' => $period,
                'status' => 'paid',
                'amount' => 0,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_can_revert_payment_to_unpaid()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $period = now()->format('Y-m');

        // First mark as paid
        DuesPayment::create([
            'member_id' => $member->id,
            'organization_unit_id' => $this->unit->id,
            'period' => $period,
            'status' => 'paid',
            'amount' => 50000,
            'paid_at' => now(),
            'recorded_by' => $this->bendahara->id,
        ]);

        // Then revert to unpaid
        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.dues.update'), [
                'member_id' => $member->id,
                'period' => $period,
                'status' => 'unpaid',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('dues_payments', [
            'member_id' => $member->id,
            'period' => $period,
            'status' => 'unpaid',
            'amount' => null,
            'paid_at' => null,
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_period()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $period = now()->format('Y-m');

        DuesPayment::create([
            'member_id' => $member->id,
            'organization_unit_id' => $this->unit->id,
            'period' => $period,
            'status' => 'paid',
            'amount' => 50000,
        ]);

        // Update existing record should work via updateOrCreate
        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.dues.update'), [
                'member_id' => $member->id,
                'period' => $period,
                'status' => 'paid',
                'amount' => 75000,
                'notes' => 'Updated amount',
            ]);

        $response->assertRedirect();

        // Should update, not create duplicate
        $this->assertEquals(1, DuesPayment::where('member_id', $member->id)->where('period', $period)->count());
        $this->assertDatabaseHas('dues_payments', [
            'member_id' => $member->id,
            'period' => $period,
            'amount' => 75000,
        ]);
    }

    public function test_dashboard_includes_dues_summary_for_bendahara()
    {
        $member1 = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $member2 = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $period = now()->format('Y-m');

        DuesPayment::create([
            'member_id' => $member1->id,
            'organization_unit_id' => $this->unit->id,
            'period' => $period,
            'status' => 'paid',
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->bendahara)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn(AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('dues_summary')
                ->where('dues_summary.total', 2)
                ->where('dues_summary.paid', 1)
                ->where('dues_summary.unpaid', 1)
        );
    }
}
