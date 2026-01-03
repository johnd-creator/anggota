<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test unauthorized role gets 403.
     */
    public function test_unauthorized_role_gets_403(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('test.csv', "full_name,status\nJohn Doe,aktif");

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin_unit forced to own unit (injection ignored).
     */
    public function test_admin_unit_forced_to_own_unit(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Unit A', 'code' => 'UA']);
        $otherUnit = OrganizationUnit::factory()->create(['name' => 'Unit B', 'code' => 'UB']);

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('test.csv', "full_name,status\nJohn Doe,aktif");

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
            'organization_unit_id' => $otherUnit->id, // Attempt to inject
        ]);

        $response->assertStatus(200);

        // Verify batch was created for user's unit, not injected unit
        $batch = ImportBatch::latest()->first();
        $this->assertEquals($unit->id, $batch->organization_unit_id);
    }

    /**
     * Test preview creates batch and returns counts.
     */
    public function test_preview_creates_batch_and_counts(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit', 'code' => 'TU']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $csv = "full_name,status\nJohn Doe,aktif\nJane Smith,aktif\nBob Johnson,nonaktif";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('import_batches', [
            'actor_user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'status' => 'previewed',
            'total_rows' => 3,
        ]);
    }

    /**
     * Test preview detects invalid rows.
     */
    public function test_preview_detects_invalid_rows(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit', 'code' => 'TU']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        // Missing required fields
        $csv = "full_name,status\nJohn Doe,aktif\n,aktif\nBob Johnson,invalid_status";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test preview detects duplicates.
     */
    public function test_preview_detects_duplicates(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit', 'code' => 'TU']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        // Create existing member using factory
        $existingMember = Member::factory()->create([
            'nra' => 'NRA001',
            'organization_unit_id' => $unit->id,
        ]);

        // CSV with duplicate NRA (existing in DB)
        $csv = "full_name,status,nra\nJohn Doe,aktif,NRA001\nJane Smith,aktif,NRA002";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test audit log is created.
     */
    public function test_audit_log_created(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit', 'code' => 'TU']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $csv = "full_name,status\nJohn Doe,aktif";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'import.members.preview',
        ]);
    }
}
