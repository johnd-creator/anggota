<?php

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Role;
use App\Models\OrganizationUnit;
use App\Models\UnionPosition;

test('store requires union_position_id exists', function(){
    Artisan::call('migrate', ['--force' => true]);
    $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);
    $unit = OrganizationUnit::factory()->create();
    $resp = post('/admin/members', [
        'full_name' => 'No Position',
        'email' => 'nopos@example.com',
        'nip' => 'NIP0000',
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
        'union_position_id' => '',
    ]);
    $resp->assertStatus(302);
    expect(session('errors'))->not()->toBeNull();
});

test('store accepts valid union_position_id', function(){
    Artisan::call('migrate', ['--force' => true]);
    $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);
    $unit = OrganizationUnit::factory()->create();
    $pos = UnionPosition::create(['name' => 'Ketua']);
    post('/admin/members', [
        'full_name' => 'Has Position',
        'email' => 'haspos@example.com',
        'nip' => 'NIP0001',
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id,
        'union_position_id' => $pos->id,
    ])->assertStatus(302);
    expect(\App\Models\Member::where('email','haspos@example.com')->where('union_position_id',$pos->id)->exists())->toBeTrue();
});
