<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;
use App\Models\Role;
use App\Models\User;
use App\Models\Member;
use App\Models\OrganizationUnit;

function makeMinimalXlsx(array $rows): string {
    $zip = new ZipArchive();
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>\n<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n  <Default Extension="xml" ContentType="application/xml"/>\n  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>\n  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>\n</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>\n</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">\n  <sheets>\n    <sheet name="Template" sheetId="1" r:id="rId1"/>\n  </sheets>\n</workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">\n  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>\n</Relationships>');
    $rowXml = '';
    foreach ($rows as $idx => $cells) {
        $r = $idx + 1;
        $rowXml .= "<row r=\"{$r}\">";
        $col = 0;
        foreach ($cells as $cell) {
            $colLetter = chr(ord('A') + $col);
            $rowXml .= "<c r=\"{$colLetter}{$r}\" t=\"inlineStr\"><is><t>" . htmlspecialchars($cell, ENT_XML1) . "</t></is></c>";
            $col++;
        }
        $rowXml .= '</row>';
    }
    $sheet = '<?xml version="1.0" encoding="UTF-8"?>\n<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">\n  <sheetData>' . $rowXml . '</sheetData>\n</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    $data = file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

test('admin_unit can import members from xlsx', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::create(['code' => '013', 'name' => 'Unit XLSX', 'address' => 'Alamat']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unit->id]);

    $rows = [
        ['full_name','email','nip','join_date','status','phone'],
        ['Excel Modern','xlsx1@example.com','NIP-X1','2025-01-07','aktif','0810000001'],
    ];
    $data = makeMinimalXlsx($rows);
    $file = UploadedFile::fake()->createWithContent('import.xlsx', $data);
    $resp = test()->actingAs($admin)->post(route('admin.members.import'), ['file' => $file]);
    $resp->assertRedirect(route('admin.members.index'));
    expect(Member::where('organization_unit_id',$unit->id)->where('email','xlsx1@example.com')->exists())->toBeTrue();
});

