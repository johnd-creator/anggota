<?php

use App\Models\User;
use function Pest\Laravel\actingAs;

test('user can see notifications and mark as read', function () {
    
    $user = User::factory()->make();
    $user->id = 9999;
    $user->setRelation('role', new App\Models\Role(['name' => 'anggota', 'label' => 'Anggota']));

    actingAs($user);
    $this->get('/notifications')->assertStatus(200);
    actingAs($user);
    $this->post('/notifications/read-all')->assertStatus(302);
});
