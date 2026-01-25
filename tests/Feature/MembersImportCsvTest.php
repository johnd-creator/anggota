<?php

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;

test('admin_unit can import members from csv and assign correct unit', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    $unit = OrganizationUnit::create(['code' => '010', 'name' => 'Unit 10', 'address' => 'Alamat']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unit->id]);

    $csv = "full_name,email,nip,join_date,status\n".
           "Fauzi,fauzi@example.com,10890210B,2025-01-01,aktif\n".
           "Budi,budi@example.com,NIP-1,2025-01-02,aktif\n";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $resp = test()->actingAs($admin)->post(route('admin.members.import'), ['file' => $file]);
    $resp->assertRedirect(route('admin.members.index'));
    expect(Member::where('organization_unit_id', $unit->id)->count())->toBeGreaterThanOrEqual(2);
});

test('duplicate email row fails but others succeed', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    $unit = OrganizationUnit::create(['code' => '011', 'name' => 'Unit 11', 'address' => 'Alamat']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unit->id]);

    Member::create(['full_name' => 'Existing', 'email' => 'dup@example.com', 'status' => 'aktif', 'organization_unit_id' => $unit->id, 'nra' => '011-2025-001', 'join_date' => now(), 'join_year' => now()->year, 'sequence_number' => 1]);

    $csv = "full_name,email,nip,join_date,status\n".
           "Row1,dup@example.com,NIP-1,2025-01-01,aktif\n".
           "Row2,row2@example.com,NIP-2,2025-01-02,aktif\n";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $resp = test()->actingAs($admin)->post(route('admin.members.import'), ['file' => $file]);
    $resp->assertRedirect(route('admin.members.index'));
    expect(Member::where('email', 'row2@example.com')->exists())->toBeTrue();
});

test('template route returns csv file', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    $unit = OrganizationUnit::create(['code' => '099', 'name' => 'Unit Test', 'address' => 'Alamat']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unit->id]);
    $resp = test()->actingAs($admin)->get(route('admin.members.import.template'));
    $resp->assertStatus(200);
});
