<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;
use App\Models\Role;
use App\Models\User;
use App\Models\Member;
use App\Models\OrganizationUnit;

test('admin_unit can import members from xls xml template', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::create(['code' => '012', 'name' => 'Unit XLS', 'address' => 'Alamat']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unit->id]);

    $xls = <<<XML
<?xml version="1.0"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <Worksheet ss:Name="Template">
    <Table>
      <Row>
        <Cell><Data ss:Type="String">full_name</Data></Cell>
        <Cell><Data ss:Type="String">email</Data></Cell>
        <Cell><Data ss:Type="String">nip</Data></Cell>
        <Cell><Data ss:Type="String">join_date</Data></Cell>
        <Cell><Data ss:Type="String">status</Data></Cell>
        <Cell><Data ss:Type="String">phone</Data></Cell>
      </Row>
      <Row>
        <Cell><Data ss:Type="String">Excel Satu</Data></Cell>
        <Cell><Data ss:Type="String">excel1@example.com</Data></Cell>
        <Cell><Data ss:Type="String">NIP-EX1</Data></Cell>
        <Cell><Data ss:Type="String">2025-01-05</Data></Cell>
        <Cell><Data ss:Type="String">aktif</Data></Cell>
        <Cell><Data ss:Type="String">0800000001</Data></Cell>
      </Row>
      <Row>
        <Cell><Data ss:Type="String">Excel Dua</Data></Cell>
        <Cell><Data ss:Type="String">excel2@example.com</Data></Cell>
        <Cell><Data ss:Type="String">NIP-EX2</Data></Cell>
        <Cell><Data ss:Type="String">2025-01-06</Data></Cell>
        <Cell><Data ss:Type="String">aktif</Data></Cell>
        <Cell><Data ss:Type="String">0800000002</Data></Cell>
      </Row>
    </Table>
  </Worksheet>
</Workbook>
XML;
    $file = UploadedFile::fake()->createWithContent('import.xls', $xls);
    $resp = test()->actingAs($admin)->post(route('admin.members.import'), ['file' => $file]);
    $resp->assertRedirect(route('admin.members.index'));
    expect(Member::where('organization_unit_id',$unit->id)->count())->toBeGreaterThanOrEqual(2);
});
