<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use App\Models\Member;
use App\Models\OrganizationUnit;

test('admin_unit sees only members in their unit on index', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unitA = OrganizationUnit::create(['code' => '010', 'name' => 'Unit A', 'address' => 'Alamat A']);
    $unitB = OrganizationUnit::create(['code' => '020', 'name' => 'Unit B', 'address' => 'Alamat B']);

    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unitA->id]);

    Member::create(['full_name' => 'Anggota A1','email' => 'a1@example.com','status' => 'aktif','organization_unit_id' => $unitA->id,'nra' => '010-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);
    Member::create(['full_name' => 'Anggota B1','email' => 'b1@example.com','status' => 'aktif','organization_unit_id' => $unitB->id,'nra' => '020-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);

    $resp = test()->actingAs($admin)->get('/admin/members');
    $resp->assertStatus(200);
    $resp->assertSee('Anggota A1');
    $resp->assertDontSee('Anggota B1');
});

test('super_admin can filter by unit and export', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unitA = OrganizationUnit::create(['code' => '011', 'name' => 'Unit SA', 'address' => 'Alamat SA']);
    $unitB = OrganizationUnit::create(['code' => '021', 'name' => 'Unit SB', 'address' => 'Alamat SB']);
    $roleSA = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $sa = User::factory()->create(['role_id' => $roleSA->id]);

    Member::create(['full_name' => 'SA Member A','email' => 'sa_a@example.com','status' => 'aktif','organization_unit_id' => $unitA->id,'nra' => '011-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);
    Member::create(['full_name' => 'SA Member B','email' => 'sa_b@example.com','status' => 'aktif','organization_unit_id' => $unitB->id,'nra' => '021-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);

    $respIndex = test()->actingAs($sa)->get('/admin/members?units[]=' . $unitA->id);
    $respIndex->assertStatus(200);
    $respIndex->assertSee('SA Member A');
    $respIndex->assertDontSee('SA Member B');

    $respExport = test()->actingAs($sa)->get(route('admin.members.export', ['unit_id' => $unitB->id]));
    $respExport->assertStatus(200);
});

test('admin_unit export is limited to their unit', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unitA = OrganizationUnit::create(['code' => '012', 'name' => 'Unit UA', 'address' => 'Alamat UA']);
    $unitB = OrganizationUnit::create(['code' => '022', 'name' => 'Unit UB', 'address' => 'Alamat UB']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unitA->id]);

    Member::create(['full_name' => 'UA1','email' => 'ua1@example.com','status' => 'aktif','organization_unit_id' => $unitA->id,'nra' => '012-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);
    Member::create(['full_name' => 'UB1','email' => 'ub1@example.com','status' => 'aktif','organization_unit_id' => $unitB->id,'nra' => '022-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);

    $resp = test()->actingAs($admin)->get(route('admin.members.export', ['unit_id' => $unitB->id]));
    $resp->assertStatus(200);
});
