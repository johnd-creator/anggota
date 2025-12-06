<?php

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\delete;
use function Pest\Laravel\put;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Role;
use App\Models\UnionPosition;

test('super admin can CRUD union positions', function(){
    Artisan::call('migrate', ['--force' => true]);
    $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);

    get(route('admin.union_positions.index'))->assertStatus(200);
    post(route('admin.union_positions.store'), ['name' => 'Koordinator', 'code' => 'KOOR'])->assertStatus(302);
    $pos = UnionPosition::where('name','Koordinator')->first();
    expect($pos)->not()->toBeNull();
    put(route('admin.union_positions.update', $pos->id), ['name' => 'Koordinator Wilayah'])->assertStatus(302);
    $pos->refresh();
    expect($pos->name)->toBe('Koordinator Wilayah');
    delete(route('admin.union_positions.destroy', $pos->id))->assertStatus(302);
    expect(UnionPosition::where('name','Koordinator Wilayah')->exists())->toBeFalse();
});

test('admin_unit cannot access union positions', function(){
    Artisan::call('migrate', ['--force' => true]);
    $role = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);
    get(route('admin.union_positions.index'))->assertStatus(403);
});
