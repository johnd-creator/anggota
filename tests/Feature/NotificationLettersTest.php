<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationLettersTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $adminUnit;
    protected $adminPusat;
    protected $bendahara;
    protected $ketua;
    protected $sekretaris;
    protected $unit;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAdminPusat = Role::create(['name' => 'admin_pusat', 'label' => 'Admin Pusat']);
        $roleBendahara = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);

        $positionKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KTU']);
        $positionSekretaris = UnionPosition::create(['name' => 'Sekretaris', 'code' => 'SEK']);

        $this->unit = OrganizationUnit::factory()->create(['code' => '010']);

        $this->superAdmin = User::factory()->create(['role_id' => $roleSuperAdmin->id]);
        $this->adminUnit = User::factory()->create(['role_id' => $roleAdminUnit->id, 'organization_unit_id' => $this->unit->id]);
        $this->adminPusat = User::factory()->create(['role_id' => $roleAdminPusat->id]);
        $this->bendahara = User::factory()->create(['role_id' => $roleBendahara->id, 'organization_unit_id' => $this->unit->id]);

        $memberKetua = Member::factory()->create(['organization_unit_id' => $this->unit->id, 'union_position_id' => $positionKetua->id]);
        $this->ketua = User::factory()->create(['role_id' => $roleAnggota->id, 'member_id' => $memberKetua->id, 'organization_unit_id' => $this->unit->id]);

        $memberSekretaris = Member::factory()->create(['organization_unit_id' => $this->unit->id, 'union_position_id' => $positionSekretaris->id]);
        $this->sekretaris = User::factory()->create(['role_id' => $roleAnggota->id, 'member_id' => $memberSekretaris->id, 'organization_unit_id' => $this->unit->id]);

        $this->category = LetterCategory::create(['name' => 'Undangan', 'code' => 'UND', 'is_active' => true]);
    }

    protected function createDraftLetter(string $signerType = 'ketua'): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => $signerType,
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'draft',
        ]);
    }

    public function test_submit_notifies_approver_and_is_idempotent()
    {
        $letter = $this->createDraftLetter('ketua');

        $this->actingAs($this->adminUnit)->post(route('letters.submit', $letter->id));
        $this->actingAs($this->adminUnit)->post(route('letters.submit', $letter->id));

        $count = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $this->ketua->id)
            ->where('type', \App\Notifications\LetterSubmittedNotification::class)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_approve_notifies_creator()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Approval Test',
            'body' => 'Body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->ketua)->post(route('letters.approve', $letter->id));

        $count = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $this->adminUnit->id)
            ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
            ->where('data->action', 'approved')
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_reject_notifies_creator()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Reject Test',
            'body' => 'Body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->ketua)->post(route('letters.reject', $letter->id), ['note' => 'Alasan']);

        $count = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $this->adminUnit->id)
            ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
            ->where('data->action', 'rejected')
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_send_notifies_member_recipient()
    {
        $member = Member::factory()->create(['organization_unit_id' => $this->unit->id]);
        $recipient = User::factory()->create(['role_id' => Role::where('name', 'anggota')->first()->id, 'member_id' => $member->id, 'organization_unit_id' => $this->unit->id]);

        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'member',
            'to_member_id' => $member->id,
            'subject' => 'Kepada Anggota',
            'body' => 'Body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'approved',
        ]);

        $this->actingAs($this->adminUnit)->post(route('letters.send', $letter->id));

        $count = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $recipient->id)
            ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
            ->where('data->action', 'sent')
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_send_notifies_unit_admins()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Kepada Unit',
            'body' => 'Body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'approved',
        ]);

        $this->actingAs($this->adminUnit)->post(route('letters.send', $letter->id));

        $countAdminUnit = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $this->adminUnit->id)
            ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
            ->where('data->action', 'sent')
            ->count();

        $countBendahara = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $this->bendahara->id)
            ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
            ->where('data->action', 'sent')
            ->count();

        $this->assertEquals(1, $countAdminUnit);
        $this->assertEquals(1, $countBendahara);
    }

    public function test_archive_notifies_admin_pusat()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'sekretaris',
            'to_type' => 'admin_pusat',
            'subject' => 'Kepada Admin Pusat',
            'body' => 'Body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'approved',
        ]);

        $this->actingAs($this->superAdmin)->post(route('letters.archive', $letter->id));

        $count = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $this->adminPusat->id)
            ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
            ->where('data->action', 'archived')
            ->count();

        $this->assertEquals(1, $count);
    }
}
