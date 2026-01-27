<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberImportCommitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Create a batch in previewed state for testing.
     */
    private function createPreviewedBatch(User $user, ?int $unitId = null): ImportBatch
    {
        // Create a test CSV file with Gmail emails (required for SSO login) and valid NRA format
        $csvContent = "full_name,status,nra,email,organization_unit_id\nJohn Doe,aktif,A-2024-001,john@gmail.com,{$unitId}\nJane Smith,aktif,A-2024-002,jane@gmail.com,{$unitId}";
        $filename = 'test_'.uniqid().'.csv';
        $path = 'imports/'.$filename;

        Storage::disk('local')->put($path, $csvContent);

        return ImportBatch::create([
            'actor_user_id' => $user->id,
            'organization_unit_id' => $unitId,
            'status' => 'previewed',
            'original_filename' => 'test.csv',
            'stored_path' => $path,
            'file_hash' => hash('sha256', $csvContent),
            'total_rows' => 2,
            'valid_rows' => 2,
            'invalid_rows' => 0,
        ]);
    }

    /**
     * Test commit creates members.
     */
    public function test_commit_creates_members(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $batch = $this->createPreviewedBatch($user, $unit->id);

        $response = $this->actingAs($user)->postJson("/admin/members/import/{$batch->id}/commit");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'completed',
            'created_count' => 2,
        ]);

        // Verify members created
        $this->assertDatabaseHas('members', ['nra' => 'A-2024-001']);
        $this->assertDatabaseHas('members', ['nra' => 'A-2024-002']);

        // Verify batch updated
        $batch->refresh();
        $this->assertEquals('completed', $batch->status);
        $this->assertNotNull($batch->committed_at);
    }

    /**
     * Test second commit is rejected (idempotent).
     */
    public function test_commit_idempotent(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $batch = $this->createPreviewedBatch($user, $unit->id);

        // First commit
        $this->actingAs($user)->postJson("/admin/members/import/{$batch->id}/commit");

        // Second commit should be rejected
        $response = $this->actingAs($user)->postJson("/admin/members/import/{$batch->id}/commit");

        $response->assertStatus(409);
        $response->assertJsonStructure(['error', 'committed_at']);
    }

    /**
     * Test unauthorized user cannot commit.
     */
    public function test_commit_unauthorized(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $actor = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $otherUser = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $batch = $this->createPreviewedBatch($actor, $unit->id);

        // Other user tries to commit
        $response = $this->actingAs($otherUser)->postJson("/admin/members/import/{$batch->id}/commit");

        $response->assertStatus(403);
    }

    /**
     * Test error CSV can be downloaded.
     */
    public function test_error_csv_download(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $batch = ImportBatch::create([
            'actor_user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'status' => 'previewed',
            'original_filename' => 'test.csv',
            'stored_path' => 'imports/dummy.csv',
            'total_rows' => 3,
            'valid_rows' => 1,
            'invalid_rows' => 2,
        ]);

        // Add some errors (structured format)
        \App\Models\ImportBatchError::create([
            'import_batch_id' => $batch->id,
            'row_number' => 2,
            'errors_json' => [
                ['field' => 'full_name', 'severity' => 'critical', 'current_value' => null, 'message' => 'full_name wajib diisi', 'expected_format' => 'Minimal 2 karakter, contoh: Budi Santoso'],
            ],
        ]);
        \App\Models\ImportBatchError::create([
            'import_batch_id' => $batch->id,
            'row_number' => 3,
            'errors_json' => [
                ['field' => 'status', 'severity' => 'critical', 'current_value' => 'invalid', 'message' => 'status tidak valid', 'expected_format' => 'Gunakan salah satu: aktif, cuti, suspended, resign, pensiun'],
            ],
        ]);

        $response = $this->actingAs($user)->get("/admin/members/import/{$batch->id}/errors");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('row_number,severity,field,current_value,message,expected_format', $content);
        $this->assertStringContainsString('full_name wajib diisi', $content);
    }

    /**
     * Test audit logs are created.
     */
    public function test_audit_logs_created(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $batch = $this->createPreviewedBatch($user, $unit->id);

        $this->actingAs($user)->postJson("/admin/members/import/{$batch->id}/commit");

        // Check commit log
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'import.members.commit',
            'subject_id' => $batch->id,
        ]);

        // Check completed log
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'import.members.completed',
            'subject_id' => $batch->id,
        ]);
    }
}
