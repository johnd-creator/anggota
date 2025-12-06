<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use App\Models\Member;
use App\Models\OrganizationUnit;

test('admin_unit sees only their unit members on mutations index and cannot submit for other unit', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unitA = OrganizationUnit::create(['code' => '010', 'name' => 'Unit A', 'address' => 'Alamat A']);
    $unitB = OrganizationUnit::create(['code' => '020', 'name' => 'Unit B', 'address' => 'Alamat B']);
    $roleAdmin = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
    $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'organization_unit_id' => $unitA->id]);

    $mA = Member::create(['full_name' => 'A Member','email' => 'a@ex.com','status' => 'aktif','organization_unit_id' => $unitA->id,'nra' => '010-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);
    $mB = Member::create(['full_name' => 'B Member','email' => 'b@ex.com','status' => 'aktif','organization_unit_id' => $unitB->id,'nra' => '020-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);

    $respIndex = test()->actingAs($admin)->get('/admin/mutations');
    $respIndex->assertStatus(200);
    $respIndex->assertSee('A Member');
    $respIndex->assertDontSee('B Member');

    $respSubmitOther = test()->actingAs($admin)->post('/admin/mutations', [
        'member_id' => $mB->id,
        'to_unit_id' => $unitA->id,
        'reason' => 'Move',
        'effective_date' => now()->toDateString(),
    ]);
    $respSubmitOther->assertStatus(403);
});

test('super_admin can submit mutation for any member', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unitA = OrganizationUnit::create(['code' => '011', 'name' => 'Unit SA', 'address' => 'Alamat SA']);
    $unitB = OrganizationUnit::create(['code' => '021', 'name' => 'Unit SB', 'address' => 'Alamat SB']);
    $roleSA = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $sa = User::factory()->create(['role_id' => $roleSA->id]);

    $mB = Member::create(['full_name' => 'SA Member B','email' => 'sab@ex.com','status' => 'aktif','organization_unit_id' => $unitB->id,'nra' => '021-2025-001','join_date' => now(),'join_year' => now()->year,'sequence_number' => 1]);

    $respSubmit = test()->actingAs($sa)->post('/admin/mutations', [
        'member_id' => $mB->id,
        'to_unit_id' => $unitA->id,
        'reason' => 'Move',
        'effective_date' => now()->toDateString(),
    ]);
    $respSubmit->assertRedirect();
});

