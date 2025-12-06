<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('member stores with kta/nip/union position and email', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::factory()->create();
    $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);

    $pos = \App\Models\UnionPosition::create(['name' => 'Ketua']);
    $resp = post('/admin/members', [
        'full_name' => 'Tester One',
        'email' => 'tester1@example.com',
        'nip' => 'NIP9999',
        'union_position_id' => $pos->id,
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
    ]);
    $resp->assertStatus(302);
    $m = \App\Models\Member::where('email','tester1@example.com')->first();
    expect($m)->not()->toBeNull();
    expect($m->nip)->toBe('NIP9999');
    expect($m->union_position_id)->toBe($pos->id);
    expect($m->kta_number)->toMatch('/^\d{3}-SPPIPS-\d{2}\d{3}$/');
});

test('validation enforces unique email/kta/nip', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);

    $pos2 = \App\Models\UnionPosition::create(['name' => 'Sekretaris']);
    $payload = [
        'full_name' => 'Tester Two',
        'email' => 'dup@example.com',
        'nip' => 'NIP8888',
        'union_position_id' => $pos2->id,
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
    ];
    post('/admin/members', $payload)->assertStatus(302);

    // duplicate should fail (email)
    $resp2 = post('/admin/members', array_merge($payload, ['full_name' => 'Tester Two B']));
    $resp2->assertStatus(302);
    expect(session('errors'))->not()->toBeNull();
});

test('invalid email format is rejected', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);

    $resp = post('/admin/members', [
        'full_name' => 'Bad Email',
        'email' => 'not-an-email',
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
    ]);
    $resp->assertStatus(302);
    expect(session('errors'))->not()->toBeNull();

});

test('update requires union_position and validates kta format when provided', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);

    $pos3 = \App\Models\UnionPosition::create(['name' => 'Anggota']);
    $m = \App\Models\Member::create([
        'full_name' => 'Updatable',
        'email' => 'upd@example.com',
        'nip' => 'NIPUPD',
        'union_position_id' => $pos3->id,
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
        'nra' => '001-24-001',
        'join_year' => (int) now()->year,
        'sequence_number' => 1,
        'kta_number' => '001-SPPIPS-24001',
    ]);

    $resp = post('/admin/members/'.$m->id, [
        '_method' => 'PUT',
        'full_name' => 'Updatable',
        'email' => 'upd@example.com',
        'nip' => 'NIPUPD',
        'union_position_id' => '',
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
        'kta_number' => 'bad-format',
    ]);
    $resp->assertStatus(302);
    expect(session('errors'))->not()->toBeNull();
});
