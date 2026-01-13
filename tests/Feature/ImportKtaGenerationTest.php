<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\MemberImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportKtaGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_generates_kta_when_missing()
    {
        // 1. Setup Data
        $unit = OrganizationUnit::factory()->create(['code' => '010']);
        $admin = User::factory()->create(['organization_unit_id' => $unit->id]);

        // 2. Prepare Import File (Without KTA column)
        // Header: full_name, email, organization_unit_id, status
        $csvContent = "full_name,email,organization_unit_id,status,join_date\nTest Import Member,test.import@example.com,{$unit->id},aktif,2024-01-01";
        $file = UploadedFile::fake()->createWithContent('members.csv', $csvContent);

        // 3. Execute Import
        $service = app(MemberImportService::class);
        $batch = $service->preview($file, $unit->id, $admin);

        // Commit the batch
        $result = $service->commit($batch);

        // 4. Assertions
        $this->assertEquals(1, $result['created_count']);

        $member = Member::where('email', 'test.import@example.com')->first();
        $this->assertNotNull($member);

        // Assert KTA is generated and follows format
        $this->assertNotNull($member->kta_number, 'KTA number should be generated');
        $this->assertStringContainsString('SPPIPS', $member->kta_number);
        $this->assertStringStartsWith((string) $unit->id, $member->kta_number);

        // Assert NRA is also present (it was already working, just ensuring no regression)
        $this->assertNotNull($member->nra);

        // Optional: Assert KTA and NRA similarity in sequence if intended
        // expected: 010-SPPIPS-24001
        // expected NRA: 010-24-001
        // The last 3 digits should match if logic works
        $ktaSeq = substr($member->kta_number, -3);
        $nraSeq = substr($member->nra, -3);
        $this->assertEquals($ktaSeq, $nraSeq, "Sequence number should match between KTA and NRA");
    }

    public function test_import_allows_alphanumeric_nip_and_optional_unit_id_for_admin()
    {
        // 1. Setup Data
        $unit = OrganizationUnit::factory()->create(['code' => '020']);
        $admin = User::factory()->create(['organization_unit_id' => $unit->id]);

        // 2. Prepare Import File 
        // - Alphanumeric NIP: "123ABC"
        // - Missing organization_unit_id (should infer from admin)
        $csvContent = "full_name,email,nip,status,join_date\nAlpha NIP User,alpha.nip@example.com,123ABC456,aktif,2024-02-01";
        $file = UploadedFile::fake()->createWithContent('members_alpha.csv', $csvContent);

        // 3. Execute Import
        $service = app(MemberImportService::class);
        $batch = $service->preview($file, $unit->id, $admin);

        // Assert no errors regarding NIP or Unit ID
        $errors = \App\Models\ImportBatchError::where('import_batch_id', $batch->id)->get();
        // dump($errors->toArray());
        $this->assertCount(0, $errors, 'Should not have validation errors for Alphanumeric NIP or missing Unit ID');

        // Commit
        $result = $service->commit($batch);

        // 4. Assertions
        $this->assertEquals(1, $result['created_count']);

        $member = Member::where('email', 'alpha.nip@example.com')->first();
        $this->assertNotNull($member);
        $this->assertEquals('123ABC456', $member->nip);
        $this->assertEquals($unit->id, $member->organization_unit_id);
    }
}
