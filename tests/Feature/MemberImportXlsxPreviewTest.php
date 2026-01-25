<?php

use App\Models\ImportBatch;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberImportXlsxPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test preview with XLSX file creates batch and counts rows.
     */
    public function test_preview_xlsx_creates_batch_and_counts(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test XLSX Unit', 'code' => 'TXU']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $xlsxContent = $this->createSimpleXlsx([
            ['full_name', 'email', 'nip', 'join_date', 'status'],
            ['John Doe XLSX', 'xlsx@example.com', '19901234567890', '2024-01-15', 'aktif'],
            ['Jane Smith XLSX', 'jane.xlsx@example.com', '19901234567891', '2024-02-20', 'aktif'],
        ]);

        $file = UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContent);

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('import_batches', [
            'actor_user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'status' => 'previewed',
            'total_rows' => 2,
        ]);
    }

    /**
     * Test preview with XLSX file detects validation errors.
     */
    public function test_preview_xlsx_detects_validation_errors(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test XLSX Unit 2', 'code' => 'TX2']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $xlsxContent = $this->createSimpleXlsx([
            ['full_name', 'status', 'email'],
            ['Valid User', 'aktif', 'valid@example.com'],
            ['', 'aktif', 'missing_name@example.com'],
            ['Invalid User', 'invalid_status', 'invalid@example.com'],
        ]);

        $file = UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContent);

        $response = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        $batch = ImportBatch::latest()->first();
        $this->assertGreaterThan(0, $batch->invalid_rows);
        $this->assertLessThan(3, $batch->valid_rows);
    }

    /**
     * Test preview with XLSX file and commit succeeds.
     */
    public function test_preview_xlsx_and_commit_succeeds(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test XLSX Unit 3', 'code' => 'TX3']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $xlsxContent = $this->createSimpleXlsx([
            ['full_name', 'email', 'nip', 'join_date', 'status'],
            ['Commit User', 'commit@example.com', '19901234567892', '2024-03-10', 'aktif'],
        ]);

        $file = UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContent);

        $previewResponse = $this->actingAs($user)->post('/admin/members/import/preview', [
            'file' => $file,
        ]);

        $previewResponse->assertStatus(200);
        $batch = ImportBatch::latest()->first();

        $commitResponse = $this->actingAs($user)->post("/admin/members/import/{$batch->id}/commit");

        $commitResponse->assertStatus(200);

        $this->assertDatabaseHas('members', [
            'full_name' => 'Commit User',
            'email' => 'commit@example.com',
            'organization_unit_id' => $unit->id,
        ]);
    }

    /**
     * Helper method to create simple XLSX file content.
     */
    private function createSimpleXlsx(array $rows): string
    {
        $zip = new ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');

        $rowXml = '';
        foreach ($rows as $idx => $cells) {
            $r = $idx + 1;
            $rowXml .= "<row r=\"{$r}\">";
            $col = 0;
            foreach ($cells as $cell) {
                $colLetter = chr(ord('A') + $col);
                $rowXml .= "<c r=\"{$colLetter}{$r}\" t=\"inlineStr\"><is><t>".htmlspecialchars($cell, ENT_XML1).'</t></is></c>';
                $col++;
            }
            $rowXml .= '</row>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>'.$rowXml.'</sheetData>
</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }
}
