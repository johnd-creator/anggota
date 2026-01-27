<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberImportIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test admin_unit cannot update member in other unit even if NRA matches.
     */
    public function test_admin_unit_cannot_update_other_unit_member(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create member in Unit B with NRA that might be imported
        $memberB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
            'nra' => 'NRA999',
            'full_name' => 'Original Name',
        ]);

        // Create a batch for Unit A with a row that has matching NRA
        $csvContent = "full_name,status,nra\nAttacker Name,aktif,NRA999";
        $filename = 'test_'.uniqid().'.csv';
        $path = 'imports/'.$filename;

        Storage::disk('local')->put($path, $csvContent);

        $batch = ImportBatch::create([
            'actor_user_id' => $adminUnitA->id,
            'organization_unit_id' => $unitA->id, // Scoped to Unit A
            'status' => 'previewed',
            'original_filename' => 'test.csv',
            'stored_path' => $path,
            'file_hash' => hash('sha256', $csvContent),
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
        ]);

        // Commit the batch
        $response = $this->actingAs($adminUnitA)->postJson("/admin/members/import/{$batch->id}/commit");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'completed',
            'error_count' => 1,
        ]);

        // SECURITY CHECK: Member in Unit B should NOT be updated
        $memberB->refresh();
        $this->assertEquals('Original Name', $memberB->full_name);

        // The import row must NOT create a duplicate member record because `nra` is globally unique.
        $newMember = Member::where('organization_unit_id', $unitA->id)->where('nra', 'NRA999')->first();
        $this->assertNull($newMember);
    }

    /**
     * Test global batch requires organization_unit_id per row.
     */
    public function test_global_batch_requires_unit_per_row(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Preview a file without unit_id in the row
        $csvContent = "full_name,status\nJohn Doe,aktif";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        // Global preview (no unit specified)
        $response = $this->actingAs($superAdmin)->post('/admin/members/import/preview', [
            'file' => $file,
            // No organization_unit_id -> global batch
        ]);

        $response->assertStatus(200);

        // Batch should show invalid rows because unit is missing
        $batch = ImportBatch::latest()->first();
        $this->assertEquals(0, $batch->valid_rows);
        $this->assertEquals(1, $batch->invalid_rows);
    }

    /**
     * Test scoped batch ignores row unit and uses batch unit.
     */
    public function test_scoped_batch_ignores_row_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Import a row that claims to be for Unit B
        $csvContent = "full_name,status,organization_unit_id,email\nTest User,aktif,{$unitB->id},test@gmail.com";
        $filename = 'test_'.uniqid().'.csv';
        $path = 'imports/'.$filename;

        Storage::disk('local')->put($path, $csvContent);

        $batch = ImportBatch::create([
            'actor_user_id' => $adminUnitA->id,
            'organization_unit_id' => $unitA->id, // Scoped to Unit A
            'status' => 'previewed',
            'original_filename' => 'test.csv',
            'stored_path' => $path,
            'file_hash' => hash('sha256', $csvContent),
            'total_rows' => 1,
            'valid_rows' => 0, // Row will be rejected due to unit mismatch
            'invalid_rows' => 1,
        ]);

        $this->actingAs($adminUnitA)->postJson("/admin/members/import/{$batch->id}/commit");

        // Member should NOT be created because row unit (B) != batch unit (A) - security check
        $member = Member::where('full_name', 'Test User')->first();
        $this->assertNull($member); // Security: row unit mismatch rejection
    }

    /**
     * Test global batch with valid unit per row works correctly.
     */
    public function test_global_batch_with_valid_unit_per_row(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Preview a file with unit_id in each row
        $csvContent = "full_name,status,organization_unit_id,email\nGlobal Import User,aktif,{$unit->id},global@gmail.com";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        // Global preview (no unit specified in request)
        $response = $this->actingAs($superAdmin)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        // Batch should be valid
        $batch = ImportBatch::latest()->first();
        $this->assertEquals(1, $batch->valid_rows);
        $this->assertEquals(0, $batch->invalid_rows);
    }
}
