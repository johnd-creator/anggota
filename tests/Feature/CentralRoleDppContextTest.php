<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\Announcement;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CentralRoleDppContextTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $dpp;

    protected OrganizationUnit $dpd;

    protected User $superAdmin;

    protected User $dpdUser;

    protected Member $dpdMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);

        $this->dpp = OrganizationUnit::factory()->create([
            'code' => 'PST',
            'name' => 'DPP',
            'organization_type' => 'DPP',
            'abbreviation' => 'DPP',
            'is_pusat' => true,
            'can_register_members' => false,
            'can_issue_kta' => false,
        ]);

        $this->dpd = OrganizationUnit::factory()->create([
            'code' => '101',
            'name' => 'DPD Asal',
            'organization_type' => 'DPD',
            'abbreviation' => 'DPD',
            'is_pusat' => false,
        ]);

        $this->superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $this->dpdMember = Member::factory()->create([
            'organization_unit_id' => $this->dpd->id,
            'status' => 'aktif',
        ]);

        $this->dpdUser = User::factory()->create([
            'member_id' => $this->dpdMember->id,
            'organization_unit_id' => $this->dpd->id,
        ]);
    }

    public function test_assigning_central_roles_forces_dpp_operational_unit(): void
    {
        foreach (['admin_pusat', 'bendahara_pusat', 'pengurus_pusat'] as $roleName) {
            $user = User::factory()->create([
                'member_id' => $this->dpdMember->id,
                'organization_unit_id' => $this->dpd->id,
            ]);

            $role = Role::where('name', $roleName)->firstOrFail();

            $response = $this->actingAs($this->superAdmin)->post(route('admin.roles.assign', $role), [
                'email' => $user->email,
            ]);

            $response->assertRedirect();

            $user->refresh();
            $this->assertSame($role->id, $user->role_id);
            $this->assertSame($this->dpp->id, $user->organization_unit_id);
            $this->assertSame($this->dpd->id, $user->linkedMember->organization_unit_id);
            $this->assertSame($this->dpp->id, $user->currentUnitId());
        }
    }

    public function test_admin_pusat_forces_ketua_approval_and_dpp_context_on_create(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        $response = $this->actingAs($adminPusat)->post('/letters', [
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat DPP',
            'body' => 'Isi surat DPP',
            'to_type' => 'unit',
            'to_unit_id' => $this->dpd->id,
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'signer_type' => 'sekretaris',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $letter = Letter::query()->latest('id')->firstOrFail();
        $this->assertSame($adminPusat->id, $letter->creator_user_id);
        $this->assertSame($this->dpp->id, $letter->from_unit_id);
        $this->assertSame('ketua', $letter->signer_type);
    }

    public function test_admin_pusat_cannot_create_letter_with_admin_pusat_destination(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        $response = $this->actingAs($adminPusat)->post('/letters', [
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat Tidak Valid',
            'body' => 'Isi surat',
            'to_type' => 'admin_pusat',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'signer_type' => 'ketua',
        ]);

        $response->assertSessionHasErrors(['to_type']);
    }

    public function test_admin_pusat_can_monitor_own_approval_queue_without_actions(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        Letter::create([
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat Monitoring Pusat',
            'body' => 'Isi',
            'from_unit_id' => $this->dpp->id,
            'to_type' => 'unit',
            'to_unit_id' => $this->dpd->id,
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'submitted',
            'signer_type' => 'ketua',
            'creator_user_id' => $adminPusat->id,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($adminPusat)->get('/letters/approvals');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Letters/Approvals')
            ->where('monitoringOnly', true)
            ->where('canTakeApprovalAction', false)
            ->where('letters.data.0.subject', 'Surat Monitoring Pusat')
        );
    }

    public function test_central_inbox_uses_pusat_recipient_scope_not_member_origin_unit(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        $unitLetter = Letter::create([
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat Ke DPD Asal',
            'body' => 'Isi',
            'from_unit_id' => $this->dpd->id,
            'to_type' => 'unit',
            'to_unit_id' => $this->dpd->id,
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'sent',
            'signer_type' => 'ketua',
            'creator_user_id' => $this->superAdmin->id,
        ]);

        $pusatLetter = Letter::create([
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat Ke Pusat',
            'body' => 'Isi',
            'from_unit_id' => $this->dpd->id,
            'to_type' => 'admin_pusat',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'sent',
            'signer_type' => 'ketua',
            'creator_user_id' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($adminPusat)->get('/letters/inbox');

        $response->assertStatus(200);
        $response->assertSee($pusatLetter->subject);
        $response->assertDontSee($unitLetter->subject);
    }

    public function test_admin_pusat_finance_operations_default_to_dpp_context(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        $response = $this->actingAs($adminPusat)->post('/finance/categories', [
            'name' => 'Kas DPP',
            'type' => 'income',
            'organization_unit_id' => $this->dpd->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $category = FinanceCategory::query()->where('name', 'Kas DPP')->firstOrFail();
        $this->assertSame($this->dpp->id, $category->organization_unit_id);
    }

    public function test_admin_pusat_export_can_filter_other_unit_with_full_pii(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        Member::factory()->create([
            'organization_unit_id' => $this->dpd->id,
            'email' => 'pusat.monitor@example.com',
            'phone' => '081234567890',
            'nip' => '12345678',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($adminPusat)
            ->get('/reports/export?type=members&unit_id=' . $this->dpd->id);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('pusat.monitor@example.com', $content);
        $this->assertStringContainsString('081234567890', $content);
        $this->assertStringContainsString('12345678', $content);
    }

    public function test_admin_pusat_finance_ledger_update_cannot_be_reassigned_to_origin_dpd(): void
    {
        $adminPusat = $this->assignCentralRole('admin_pusat');

        $category = FinanceCategory::create([
            'organization_unit_id' => $this->dpp->id,
            'name' => 'Kas Operasional DPP',
            'type' => 'income',
            'created_by' => $adminPusat->id,
        ]);

        $ledger = FinanceLedger::create([
            'organization_unit_id' => $this->dpp->id,
            'finance_category_id' => $category->id,
            'type' => 'income',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'description' => 'Penerimaan awal',
            'status' => 'draft',
            'created_by' => $adminPusat->id,
        ]);

        $response = $this->actingAs($adminPusat)->put("/finance/ledgers/{$ledger->id}", [
            'date' => now()->toDateString(),
            'finance_category_id' => $category->id,
            'type' => 'income',
            'amount' => 150000,
            'description' => 'Penerimaan diperbarui',
            'organization_unit_id' => $this->dpd->id,
        ]);

        $response->assertRedirect(route('finance.ledgers.index'));
        $response->assertSessionHasNoErrors();

        $ledger->refresh();
        $this->assertSame($this->dpp->id, $ledger->organization_unit_id);
        $this->assertSame(150000.0, (float) $ledger->amount);
    }

    public function test_pengurus_pusat_member_aspirations_and_admin_announcements_use_central_access(): void
    {
        $pengurusPusat = $this->assignCentralRole('pengurus_pusat');

        $this->actingAs($pengurusPusat)
            ->get(route('member.aspirations.index'))
            ->assertOk();

        $this->actingAs($pengurusPusat)
            ->get(route('admin.announcements.index'))
            ->assertOk();

        $response = $this->actingAs($pengurusPusat)->post(route('admin.announcements.store'), [
            'title' => 'Info DPP',
            'body' => 'Konten pusat',
            'scope_type' => 'unit',
            'organization_unit_id' => $this->dpd->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));

        $announcement = Announcement::query()->latest('id')->firstOrFail();
        $this->assertSame($this->dpp->id, $announcement->organization_unit_id);
    }

    public function test_pengurus_pusat_can_access_letter_qr_and_dashboard_uses_global_monitoring_with_central_inbox(): void
    {
        $pengurusPusat = $this->assignCentralRole('pengurus_pusat');

        Member::factory()->create([
            'organization_unit_id' => $this->dpp->id,
            'status' => 'aktif',
        ]);

        $approvedLetter = Letter::create([
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat Final Pusat',
            'body' => 'Isi final',
            'from_unit_id' => $this->dpp->id,
            'to_type' => 'admin_pusat',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'approved',
            'signer_type' => 'ketua',
            'verification_token' => (string) \Illuminate\Support\Str::uuid(),
            'creator_user_id' => $this->superAdmin->id,
            'approved_at' => now(),
            'approved_by_user_id' => $this->superAdmin->id,
        ]);

        Letter::create([
            'letter_category_id' => LetterCategory::query()->firstOrFail()->id,
            'subject' => 'Surat DPD',
            'body' => 'Isi dpd',
            'from_unit_id' => $this->dpd->id,
            'to_type' => 'unit',
            'to_unit_id' => $this->dpd->id,
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'sent',
            'signer_type' => 'ketua',
            'creator_user_id' => $this->superAdmin->id,
        ]);

        $this->actingAs($pengurusPusat)
            ->get(route('letters.qr', $approvedLetter))
            ->assertOk();

        $response = $this->actingAs($pengurusPusat)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('dashboard.members_by_unit', 2)
                ->where('letters.unread', 1)
                ->where('finance.unit_name', 'Global')
        );
    }

    protected function assignCentralRole(string $roleName): User
    {
        $user = $this->dpdUser->fresh();
        $user->role_id = Role::where('name', $roleName)->firstOrFail()->id;
        $user->organization_unit_id = $this->dpp->id;
        $user->save();

        return $user->fresh();
    }
}
