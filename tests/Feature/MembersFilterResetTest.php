<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use App\Models\Member;
use App\Models\OrganizationUnit;

test('super_admin filter units then reset shows all members', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit10 = OrganizationUnit::create(['code' => '010', 'name' => 'UBP Banten 1 Suralaya', 'address' => 'Alamat']);
    $unit01 = OrganizationUnit::create(['code' => '001', 'name' => 'Unit 1', 'address' => 'Alamat']);
    $roleSA = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $sa = User::factory()->create(['role_id' => $roleSA->id]);

    Member::create(['full_name' => 'Anggota Unit 10','email' => 'u10@example.com','status' => 'aktif','organization_unit_id' => $unit10->id,'nra' => '010-SPPIPS-24001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);
    Member::create(['full_name' => 'Anggota Unit 1','email' => 'u1@example.com','status' => 'aktif','organization_unit_id' => $unit01->id,'nra' => '001-SPPIPS-25001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);

    $respFiltered = test()->actingAs($sa)->get('/admin/members?units[]=' . $unit10->id);
    $respFiltered->assertStatus(200);
    $respFiltered->assertSee('Anggota Unit 10');
    $respFiltered->assertDontSee('u1@example.com');

    $respReset = test()->actingAs($sa)->get('/admin/members');
    $respReset->assertStatus(200);
    $respReset->assertSee('u10@example.com');
    $respReset->assertSee('u1@example.com');
});
