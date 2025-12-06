<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('export growth returns csv with months', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit = OrganizationUnit::factory()->create();
    // seed some members
    Member::create([
        'full_name' => 'A', 'email' => 'a@example.com',
        'employment_type' => 'organik', 'status' => 'aktif', 'join_date' => now()->toDateString(),
        'organization_unit_id' => $unit->id, 'nra' => 'NRA1', 'join_year' => (int) now()->year, 'sequence_number' => 1,
    ]);

    $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);
    actingAs($user);

    $resp = post('/reports/growth/export', ['unit_id' => $unit->id]);
    $resp->assertStatus(200);
    $resp->assertHeader('content-type', 'text/csv; charset=utf-8');
});
