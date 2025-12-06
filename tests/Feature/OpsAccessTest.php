<?php

use App\Models\User;
use function Pest\Laravel\actingAs;

test('ops center only accessible to super admin', function () {
    
    $super = User::factory()->make();
    $adminUnit = User::factory()->make();
    $anggota = User::factory()->make();
    $super->id = 7001; $adminUnit->id = 7002; $anggota->id = 7003;

    $super->setRelation('role', new App\Models\Role(['name' => 'super_admin', 'label' => 'Super Admin']));
    $adminUnit->setRelation('role', new App\Models\Role(['name' => 'admin_unit', 'label' => 'Admin Unit']));
    $anggota->setRelation('role', new App\Models\Role(['name' => 'anggota', 'label' => 'Anggota']));

    actingAs($super);
    $this->get('/ops')->assertStatus(200);
    actingAs($adminUnit);
    $this->get('/ops')->assertStatus(403);
    actingAs($anggota);
    $this->get('/ops')->assertStatus(403);
});
