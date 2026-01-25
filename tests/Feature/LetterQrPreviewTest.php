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
use Tests\TestCase;

class LetterQrPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminUnit;
    protected User $ketua;
    protected OrganizationUnit $unit;
    protected LetterCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $positionKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KTU']);

        $this->unit = OrganizationUnit::factory()->create(['code' => '010']);

        $this->superAdmin = User::factory()->create([
            'role_id' => $roleSuperAdmin->id,
        ]);

        $this->adminUnit = User::factory()->create([
            'role_id' => $roleAdminUnit->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $memberKetua = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'union_position_id' => $positionKetua->id,
        ]);
        $this->ketua = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberKetua->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->category = LetterCategory::create([
            'name' => 'Undangan',
            'code' => 'UND',
            'is_active' => true,
        ]);
    }

    protected function createLetter(string $status): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Test Letter',
            'body' => 'Test body content',
            'status' => $status,
            'verification_token' => \Illuminate\Support\Str::uuid(),
            'approved_at' => $status === 'approved' ? now() : null,
            'approved_by_user_id' => $status === 'approved' ? $this->ketua->id : null,
        ]);
    }

    public function test_approved_letter_preview_has_qr_and_is_final_true(): void
    {
        $letter = $this->createLetter('approved');

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.preview', $letter->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Preview')
                ->where('isFinal', true)
                ->has('qrBase64')
        );
    }

    public function test_draft_letter_preview_has_no_qr_and_is_final_false(): void
    {
        $letter = $this->createLetter('draft');

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.preview', $letter->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Preview')
                ->where('isFinal', false)
                ->where('qrBase64', null)
        );
    }

    public function test_submitted_letter_preview_has_no_qr_and_is_final_false(): void
    {
        $letter = $this->createLetter('submitted');

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.preview', $letter->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Preview')
                ->where('isFinal', false)
                ->where('qrBase64', null)
        );
    }

    public function test_approved_letter_qr_endpoint_returns_200_with_image(): void
    {
        $letter = $this->createLetter('approved');

        $response = $this->actingAs($this->adminUnit)
            ->get("/letters/{$letter->id}/qr.png");

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertTrue(
            in_array($contentType, ['image/png', 'image/svg+xml']),
            "Expected Content-Type to be image/png or image/svg+xml, but got {$contentType}"
        );
    }

    public function test_draft_letter_qr_endpoint_returns_403(): void
    {
        $letter = $this->createLetter('draft');

        $response = $this->actingAs($this->adminUnit)
            ->get("/letters/{$letter->id}/qr.png");

        $response->assertStatus(403);
    }

    public function test_submitted_letter_qr_endpoint_returns_403(): void
    {
        $letter = $this->createLetter('submitted');

        $response = $this->actingAs($this->adminUnit)
            ->get("/letters/{$letter->id}/qr.png");

        $response->assertStatus(403);
    }

    public function test_sent_letter_preview_has_qr_and_is_final_true(): void
    {
        $letter = $this->createLetter('approved');
        $letter->update(['status' => 'sent']);

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.preview', $letter->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Preview')
                ->where('isFinal', true)
                ->has('qrBase64')
        );
    }

    public function test_archived_letter_preview_has_qr_and_is_final_true(): void
    {
        $letter = $this->createLetter('approved');
        $letter->update(['status' => 'archived']);

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.preview', $letter->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Preview')
                ->where('isFinal', true)
                ->has('qrBase64')
        );
    }
}
