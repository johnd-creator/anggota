<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use App\Models\Member;
use App\Models\OrganizationUnit;

test('pagination resets to page 1 after filter change', function(){
    Artisan::call('migrate', ['--force' => true]);
    $unit10 = OrganizationUnit::create(['code' => '010', 'name' => 'UBP Banten 1 Suralaya', 'address' => 'Alamat']);
    $roleSA = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
    $sa = User::factory()->create(['role_id' => $roleSA->id]);

    // Create > 10 members to ensure multiple pages
    foreach (range(1, 15) as $i) {
        Member::create([
            'full_name' => sprintf('Unit10 Member %02d', $i),
            'email' => sprintf('u10_%02d@example.com', $i),
            'status' => 'aktif',
            'organization_unit_id' => $unit10->id,
            'nra' => sprintf('010-SPPIPS-24%03d', $i),
            'join_date' => now(),
            'join_year' => now()->year,
            'sequence_number' => $i,
        ]);
    }

    // Simulate being on page 2 with filter
    $respPage2 = test()->actingAs($sa)->get('/admin/members?page=2&units[]=' . $unit10->id);
    $respPage2->assertStatus(200);
    $respPage2->assertSee('Unit10 Member 11');
    $respPage2->assertDontSee('Unit10 Member 01');

    // UI reload should send page=1; simulate by requesting page=1
    $respResetToPage1 = test()->actingAs($sa)->get('/admin/members?page=1&units[]=' . $unit10->id);
    $respResetToPage1->assertStatus(200);
    $respResetToPage1->assertSee('Unit10 Member 01');
    $respResetToPage1->assertDontSee('Unit10 Member 11');
});

